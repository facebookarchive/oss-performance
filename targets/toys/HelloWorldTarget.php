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

final class HelloWorldTarget extends PerfTarget {
  public function getSanityCheckPath(): string {
    return '/helloworld.php';
  }

  public function getSanityCheckString(): string {
    return 'Hello, world';
  }
  public function getSourceRoot(): string {
    return __DIR__.'/toys';
  }
}
