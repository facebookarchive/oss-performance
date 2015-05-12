<?hh
/*
 *  Copyright (c) 2014, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

final class Siege extends Process {
  use SiegeStats;

  private ?string $logfile;

  public function __construct(
    private PerfOptions $options,
    private PerfTarget $target,
    private RequestMode $mode,
  ) {
    parent::__construct($options->siege);
    $this->suppress_stdout = true;

    if (!$options->skipVersionChecks) {
      $version_line = trim(
        exec(escapeshellarg($options->siege).' --version 2>&1 | head -n 1')
      );
      $bad_prefix = 'SIEGE 3';
      if (substr($version_line, 0, strlen($bad_prefix)) === $bad_prefix) {
        fprintf(
          STDERR,
          "WARNING: Siege 3.0.0-3.0.7 sends an incorrect HOST header to ports ".
          "other than :80 and :443. Siege 3.0.8 and 3.0.9 sometimes sends full ".
          "URLs as paths. You are using '%s'.\n\n".
          "You can specify a path to siege 2.7x  with the ".
          "--siege=/path/to/siege option. If you have patched siege to fix ".
          "these issues, pass --skip-version-checks.\n",
          $version_line,
        );
        exit(1);
      }
    }

    if ($mode === RequestModes::BENCHMARK) {
      $this->logfile = tempnam($options->tempDir, 'siege');
    }
  }

  public function __destruct() {
    $logfile = $this->logfile;
    if ($logfile !== null && file_exists($logfile)) {
      unlink($logfile);
    }
  }

  public function start(): void {
    parent::startWorker(
      $this->options->daemonOutputFileName('siege'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
  }

  <<__Override>>
  public function getExecutablePath(): string {
    if ($this->options->noTimeLimit) {
      return parent::getExecutablePath();
    }
    // Siege calls non-signal-safe functions from it's log function, which
    // it calls from signal handlers. Leads to hang.
    return 'timeout';
  }

  <<__Override>>
  protected function getArguments(): Vector<string> {
    $urls_file = tempnam($this->options->tempDir, 'urls');
    $urls = file_get_contents($this->target->getURLsFile());
    $urls = str_replace(
      '__HTTP_PORT__',
      (string) PerfSettings::HttpPort(),
      $urls,
    );
    $urls = str_replace(
      '__HTTP_HOST__',
      gethostname(),
      $urls
    );
    file_put_contents($urls_file, $urls);

    $arguments = Vector {};
    if (!$this->options->noTimeLimit) {
      $arguments = Vector {
        // See Siege::getExecutablePath()  - these arguments get passed to
        // timeout
        '--signal=9',
        '5m',
        parent::getExecutablePath(),
      };
    }
    $siege_rc = $this->target->getSiegeRCPath();
    if ($siege_rc !== null) {
      $arguments->addAll(Vector {
         '-R', $siege_rc,
      });
    }

    switch ($this->mode) {
      case RequestModes::WARMUP:
        $arguments->addAll(Vector {
          '-c', (string) PerfSettings::WarmupConcurrency(),
          '-r', (string) PerfSettings::WarmupRequests(),
          '-f', $urls_file,
          '--benchmark',
          '--log=/dev/null',
        });
        return $arguments;
      case RequestModes::WARMUP_MULTI:
        $arguments->addAll(Vector {
          '-c', (string) PerfSettings::BenchmarkConcurrency(),
          '-t', '1M',
          '-f', $urls_file,
          '--benchmark',
          '--log=/dev/null',
        });
        return $arguments;
      case RequestModes::BENCHMARK:
        $arguments->addAll(Vector {
          '-c', (string) PerfSettings::BenchmarkConcurrency(),
          '-f', $urls_file,
          '--benchmark',
          '--log='.$this->logfile,
        });
        
        if (!$this->options->noTimeLimit) { 
          $arguments->add('-t');
          $arguments->add(PerfSettings::BenchmarkTime());
        }
        return $arguments;
      default:
        invariant_violation(
          'Unexpected request mode: '.(string)$this->mode
        );
    }
  }

  protected function getLogFilePath(): string {
    $logfile = $this->logfile;
    invariant(
      $logfile !== null,
      'Tried to get log file path without a logfile'
    );
    return $logfile;
  }
}
