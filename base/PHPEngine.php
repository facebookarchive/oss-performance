<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

abstract class PHPEngine extends Process {
  public abstract function __toString(): string;
  public function writeStats(): void {}

  public function needsRetranslatePause(): bool { return false; }
  public function queueEmpty(): bool { return true; }
}
