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

function perf_main(Vector<string> $argv): void {
  $data = PerfRunner::RunWithArgv($argv);
  print json_encode($data, JSON_PRETTY_PRINT)."\n";
}

perf_main(new Vector($argv));
