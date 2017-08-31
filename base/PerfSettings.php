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

final class PerfSettings {

  ///// Benchmark Settings /////

  // Per concurrent thread - so, total number of requests made during warmup
  // is WarmupRequests * WarmupConcurrency
  public static function WarmupRequests(): int {
    return 300;
  }

  public static function WarmupConcurrency(): int {
    return 1;
  }

  public static function BenchmarkTime(): string {
    // [0-9]+[SMH]
    return '1M'; // 1 minute
  }

  ///// Server Settings /////

  public static function HttpPort(): int {
    return 8090;
  }

  public static function HttpAdminPort(): int {
    return 8091;
  }

  public static function BackendPort(): int {
    return 8092;
  }

  public static function BackendAdminPort(): int {
    return 8093;
  }

  public static function SleepTime(): int {
    return 180;
  }

  public static function NumRuns(): int {
    return 7;
  }
}
