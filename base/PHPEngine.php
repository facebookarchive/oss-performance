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

abstract class PHPEngine extends Process {
  public abstract function __toString(): string;
  public function writeStats(): void {}

  public function needsRetranslatePause(): bool { return false; }
  public function queueEmpty(): bool { return true; }
}
