<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class HelloWorldTarget extends PerfTarget {
  public function getSanityCheckPath(): string {
    return '/helloworld.php';
  }

  public function getSanityCheckString(): string {
    return 'Hello, world';
  }
  public function getSourceRoot(): string {
    return __DIR__;
  }
}
