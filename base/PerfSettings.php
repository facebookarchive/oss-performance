<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
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
}
