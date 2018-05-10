<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class PHP5Daemon extends PHPEngine {
  private PerfTarget $target;

  public function __construct(private PerfOptions $options) {
    $this->target = $options->getTarget();
    parent::__construct((string) $options->php5);

    if ($options->fpm) {
      $output = [];
      $check_command = implode(
        ' ',
        (Vector {
           $options->php5,
           '-i',
           '-c',
           OSS_PERFORMANCE_ROOT.'/conf',
         })->map($x ==> escapeshellarg($x)),
      );

      // Basic check for opcode caching.
      if ($options->traceSubProcess) {
        fprintf(STDERR, "%s\n", $check_command);
      }
      exec($check_command, &$output);
      $check = array_search('Opcode Caching => Up and Running', $output, true);
      invariant($check, 'Got invalid output from php-fpm -i');
    } else {
      $output = [];
      $check_command = implode(
        ' ',
        (Vector {
           $options->php5,
           '-q',
           '-c',
           OSS_PERFORMANCE_ROOT.'/conf',
           __dir__.'/php-src_config_check.php',
         })->map($x ==> escapeshellarg($x)),
      );

      if ($options->traceSubProcess) {
        fprintf(STDERR, "%s\n", $check_command);
      }
      exec($check_command, &$output);
      $checks = json_decode(implode("\n", $output), /* as array = */ true);
      invariant($checks, 'Got invalid output from php-src_config_check.php');
      BuildChecker::Check(
        $options,
        (string) $options->php5,
        $checks,
        Set {'PHP_VERSION', 'PHP_VERSION_ID'},
      );
    }
  }

  public function start(): void {
    parent::startWorker(
      $this->options->daemonOutputFileName('php5'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
  }

  protected function getArguments(): Vector<string> {
    if ($this->options->cpuBind) {
      $this->cpuRange = $this->options->daemonProcessors;
    }
    if ($this->options->fpm) {
      echo 'Creating PHP FPM config';
      $path = $this->options->tempDir.'/php-fpm.conf';
      $config = file_get_contents(OSS_PERFORMANCE_ROOT.'/conf/php-fpm.conf.in');
      $config = str_replace(
        "__FASTCGI_PORT__",
        PerfSettings::BackendPort(),
        $config
      );
      $config = str_replace(
        "__CHILDREN__",
        $this->options->serverThreads,
        $config
      );
      $config = str_replace(
        "__TMP_DIR__",
        $this->options->tempDir,
        $config
      );
      file_put_contents($path, $config);

      $args = Vector {
        '-F',
        '--fpm-config',
        $path,
        '-c',
        OSS_PERFORMANCE_ROOT.'/conf/',
      };
    } else {
      $args = Vector {
        '-b',
        '127.0.0.1:'.PerfSettings::BackendPort(),
        '-c',
        OSS_PERFORMANCE_ROOT.'/conf/',
      };
    }

    if (count($this->options->phpExtraArguments) > 0) {
      $args->addAll($this->options->phpExtraArguments);
    }
    return $args;
  }

  public function getPid(): ?int {
    $pid = parent::getPid();
    if ($pid === null) {
      return null;
    }

    // proc_open() uses the shell to spawn PHP.
    // Bash just does an exec() in simple cases, so we already have the right
    // PID.
    if ($this->isPHPCGIProcess($pid)) {
      return $pid;
    }

    // Dash always does a fork() + exec(), so we actually want the PID of one
    // of the children.
    foreach (glob('/proc/*') as $candidate) {
      if (!preg_match(',^/proc/[0-9]+(/|$),', $candidate)) {
        continue;
      }
      if (!file_exists($candidate.'/stat')) {
        continue;
      }
      $stat = explode(' ', file_get_contents($candidate.'/stat'));
      $cpid = (int) $stat[0];
      $ppid = (int) $stat[3];
      if ($ppid === $pid && $this->isPHPCGIProcess($cpid)) {
        return $cpid;
      }
    }
    return null;
  }

  private function isPHPCGIProcess(int $pid): bool {
    // Allow 'php-cgi', 'php5-cgi', 'php7-cgi-20141209', etc
    $exe = '/proc/'.$pid.'/exe';
    if (!file_exists($exe)) {
      return false;
    }
    return (bool) preg_match('/php.*-cgi/', readlink($exe)) ||
           (bool) preg_match('/php.*-fpm/', readlink($exe));
  }

  protected function getEnvironmentVariables(): Map<string, string> {
    return Map {
      'OSS_PERF_TARGET' => (string) $this->target,
      'PHP_FCGI_CHILDREN' => (string) $this->options->phpFCGIChildren,
      'PHP_FCGI_MAX_REQUESTS' => '0',
    };
  }

  public function __toString(): string {
    return (string) $this->options->php5;
  }
}
