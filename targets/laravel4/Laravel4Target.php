<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class Laravel4Target extends PerfTarget {
  public function __construct(private PerfOptions $options) {}

  protected function getSanityCheckString(): string {
    return 'You have arrived';
  }

  public function install(): void {
    $src_dir = $this->options->srcDir;
    if ($src_dir) {
      Utils::CopyDirContents($src_dir, $this->getSourceRoot());
    } else {
      shell_exec(
        $this->safeCommand(
          Vector {
            'tar',
            '-C',
            $this->options->tempDir,
            '-zxf',
            __DIR__.'/laravel-4.2.0.tar.gz',
          },
        ),
      );
      shell_exec(
        $this->safeCommand(
          Vector {
            'tar',
            '-C',
            $this->options->tempDir.'/laravel-4.2.0',
            '-jxf',
            __DIR__.'/vendor.tar.bz2',
          },
        ),
      );
    }

  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/laravel-4.2.0/public';
  }
}
