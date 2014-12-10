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


final class LaravelTarget extends PerfTarget {
  public function __construct(
    private PerfOptions $options,
  ) {
  }

  protected function getSanityCheckString(): string {
    return 'You have arrived';
  }

  public function install(): void {
    shell_exec($this->safeCommand(Vector {
      'tar',
      '-C', $this->options->tempDir,
      '-zxf',
      __DIR__.'/laravel/laravel-4.2.0.tar.gz'
    }));
    shell_exec($this->safeCommand(Vector {
      'tar',
      '-C', $this->options->tempDir.'/laravel-4.2.0',
      '-jxf',
      __DIR__.'/laravel/vendor.tar.bz2'
    }));
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/laravel-4.2.0/public';
  }
}
