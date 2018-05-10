<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class FibonacciTarget extends PerfTarget {
  public function getSanityCheckPath(): string {
    return '/fibonacci.php';
  }

  public function getSanityCheckString(): string {
    return 'int(10946)';
  }
  public function getSourceRoot(): string {
    return __DIR__;
  }
}
