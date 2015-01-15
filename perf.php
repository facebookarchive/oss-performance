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

require_once('base/cli-init.php');

function perf_main(Vector<string> $argv): void {
  $data = PerfRunner::RunWithArgv($argv);
  print json_encode($data, JSON_PRETTY_PRINT)."\n";
}

perf_main(new Vector($argv));
