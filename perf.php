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

if (!file_exists('vendor/autoload.php')) {
  fprintf(
    STDERR, "%s\n",
    'Autoload map not found. Please install composer (see getcomposer.org), '.
    'and run "composer install" from this directory.'
  );
  exit(1);
}

require_once('vendor/autoload.php');
const OSS_PERFORMANCE_ROOT = __DIR__;

function print_progress(string $out): void {
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

function run_benchmark(
  PerfOptions $options,
  PHPEngine $php_engine,
) {
  // As this is a CLI script, we should use the system timezone. Suppress
  // the error.
  error_reporting(error_reporting() ^ E_STRICT);
  $target = $options->getTarget();
  print_progress('Installing framework');
  $target->install();

  print_progress('Starting Nginx');
  $nginx = new NginxDaemon($options, $target);
  $nginx->start();
  Process::sleepSeconds($options->delayNginxStartup);
  invariant($nginx->isRunning(), 'Failed to start nginx');

  print_progress('Starting PHP Engine');
  $php_engine->start();
  Process::sleepSeconds($options->delayPhpStartup);
  invariant(
    $php_engine->isRunning(),
    'Failed to start '.get_class($php_engine)
  );

  if ($target->needsUnfreeze()) {
    print_progress('Unfreezing framework');
    $target->unfreeze($options);
  }

  if ($options->skipSanityCheck) {
    print_progress('Skipping sanity check');
  } else {
    print_progress('Running sanity check');
    $target->sanityCheck();
  }

  print_progress('Starting Siege for warmup');
  $siege = new Siege($options, $target, RequestModes::WARMUP);
  $siege->start();
  invariant($siege->isRunning(), 'Failed to start siege');
  $siege->wait();

  invariant(!$siege->isRunning(), 'Siege is still running :/');
  invariant($php_engine->isRunning(), get_class($php_engine).' crashed');

  print_progress('Clearing nginx access.log');
  $nginx->clearAccessLog();

  print_progress('Running Siege for benchmark');
  $siege = new Siege($options, $target, RequestModes::BENCHMARK);
  $siege->start();
  invariant($siege->isRunning(), 'Siege failed to start');
  $siege->wait();

  print_progress('Collecting results');
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
  print(json_encode($combined_stats, JSON_PRETTY_PRINT)."\n");

  print_progress('All done');
  $php_engine->stop();
}

function perf_main($argv) {
  SystemChecks::CheckAll();
  if (getmyuid() === 0) {
    fwrite(STDERR, "Run this script as a regular user.\n");
    exit(1);
  }

  $options = new PerfOptions($argv);

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

  $engine = null;

  if ($options->php5) {
    $engine = new PHP5Daemon($options);
  }
  if ($options->hhvm) {
    $engine = new HHVMDaemon($options);
  }
  if ($engine === null) {
    fprintf(
      STDERR,
      'Either --php5=/path/to/php-cgi or --hhvm=/path/to/hhvm '.
      'must be specified'
    );
    exit(1);
  }

  run_benchmark($options, $engine);
}

perf_main($argv);
