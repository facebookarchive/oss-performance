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

type PerfResult = Map<string, Map<string, num>>;

final class PerfRunner {
  public static function RunWithArgv(
    Vector<string> $argv,
  ): PerfResult {
    $options = new PerfOptions($argv);
    return self::RunWithOptions($options);
  }

  public static function RunWithOptions(
    PerfOptions $options,
  ): PerfResult {
    // If we exit cleanly, Process::__destruct() gets called, but it doesn't
    // if we're killed by Ctrl-C. This tends to leak php-cgi or hhvm processes -
    // trap the signal so we can clean them up.
    pcntl_signal(
      SIGINT,
      function() {
        Process::cleanupAll();
        exit();
      }
    );

    $php_engine = null;

    if ($options->php5) {
      $php_engine = new PHP5Daemon($options);
    }
    if ($options->hhvm) {
      $php_engine = new HHVMDaemon($options);
    }
    invariant($php_engine !== null, 'failed to initialize a PHP engine');

    return self::RunWithOptionsAndEngine($options, $php_engine);
  }

  private static function RunWithOptionsAndEngine(
    PerfOptions $options,
    PHPEngine $php_engine,
  ): PerfResult {
    $options->validate();
    $target = $options->getTarget();

    self::PrintProgress('Configuration: '.$target.' on '.$php_engine);
    self::PrintProgress('Installing framework');

    $target->install();

    if ($options->setUpTest != null) {
      $command = "OSS_PERF_PHASE=" . "setUp"
         . " " . "OSS_PERF_TARGET=" . (string) $target
         . " " . $options->setUpTest
         ;
      self::PrintProgress('Starting setUpTest ' . $command);
      shell_exec($command);
      self::PrintProgress('Finished setUpTest ' . $command);
    } else {
      self::PrintProgress('There is no setUpTest');
    }

    self::PrintProgress('Starting Nginx');
    $nginx = new NginxDaemon($options, $target);
    $nginx->start();
    Process::sleepSeconds($options->delayNginxStartup);
    invariant($nginx->isRunning(), 'Failed to start nginx');

    self::PrintProgress('Starting PHP Engine');
    $php_engine->start();
    Process::sleepSeconds($options->delayPhpStartup);
    invariant(
      $php_engine->isRunning(),
      'Failed to start '.get_class($php_engine)
    );

    if ($target->needsUnfreeze()) {
      self::PrintProgress('Unfreezing framework');
      $target->unfreeze($options);
    }

    if ($options->skipSanityCheck) {
      self::PrintProgress('Skipping sanity check');
    } else {
      self::PrintProgress('Running sanity check');
      $target->sanityCheck();
    }

    self::PrintProgress('Starting Siege for single request warmup');
    $siege = new Siege($options, $target, RequestModes::WARMUP);
    $siege->start();
    invariant($siege->isRunning(), 'Failed to start siege');
    $siege->wait();

    invariant(!$siege->isRunning(), 'Siege is still running :/');
    invariant($php_engine->isRunning(), get_class($php_engine).' crashed');

    self::PrintProgress('Starting Siege for multi request warmup');
    $siege = new Siege($options, $target, RequestModes::WARMUP_MULTI);
    $siege->start();
    invariant($siege->isRunning(), 'Failed to start siege');
    $siege->wait();

    invariant(!$siege->isRunning(), 'Siege is still running :/');
    invariant($php_engine->isRunning(), get_class($php_engine).' crashed');

    self::PrintProgress('Clearing nginx access.log');
    $nginx->clearAccessLog();

    self::PrintProgress('Running Siege for benchmark');
    $siege = new Siege($options, $target, RequestModes::BENCHMARK);
    $siege->start();
    invariant($siege->isRunning(), 'Siege failed to start');
    $siege->wait();

    self::PrintProgress('Collecting results');
    $siege_stats = $siege->collectStats();
    $nginx_stats = $nginx->collectStats();

    $combined_stats = Map { };
    foreach ($siege_stats as $page => $stats) {
      if ($combined_stats->containsKey($page)) {
        $combined_stats[$page]->setAll($stats);
      } else {
        $combined_stats[$page] = $stats;
      }
    }
    foreach ($nginx_stats as $page => $stats) {
      if ($combined_stats->containsKey($page)) {
        $combined_stats[$page]->setAll($stats);
      } else {
        $combined_stats[$page] = $stats;
      }
    }

    if (!$options->verbose) {
      $combined_stats = $combined_stats->filterWithKey(
        ($k, $v) ==> $k === 'Combined'
      );
    } else {
      ksort($combined_stats);
    }
    $combined_stats['Combined']['canonical'] = (int) !$options->notBenchmarking;

    self::PrintProgress('Collecting TC/PCRE data');
    $php_engine->writeStats();

    if ($options->waitAtEnd) {
      self::PrintProgress('Press Enter to shutdown the server');
      fread(STDIN, 1);
    }
    $php_engine->stop();

    if ($options->tearDownTest != null) {
      $command = "OSS_PERF_PHASE=" . "tearDown"
         . " " . "OSS_PERF_TARGET=" . (string) $target
         . " " . $options->tearDownTest
         ;
      self::PrintProgress('Starting tearDownTest ' . $command);
      shell_exec($command);
      self::PrintProgress('Finished tearDownTest ' . $command);
    } else {
      self::PrintProgress('There is no tearDownTest');
    }

    return $combined_stats;
  }

  private static function PrintProgress(string $out): void {
    $timestamp = strftime('%Y-%m-%d %H:%M:%S %Z');
    $len = max(strlen($out), strlen($timestamp));
    fprintf(
      STDERR,
      "\n%s\n** %s\n** %s\n",
      str_repeat('*', $len + 3), // +3 for '** '
      $timestamp,
      $out,
    );
  }
}
