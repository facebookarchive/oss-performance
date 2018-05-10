<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class CodeIgniterTarget extends PerfTarget {
  public function __construct(private PerfOptions $options) {}

  protected function getSanityCheckString(): string {
    return
      'The page you are looking at is being generated '.
      'dynamically by CodeIgniter.';
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
            __DIR__.'/CodeIgniter-2.2.0.tar.gz',
          },
        ),
      );
    }

    $index_path = $this->options->tempDir.'/CodeIgniter-2.2.0/index.php';
    $index = file_get_contents($index_path);
    $index = str_replace(
      "define('ENVIRONMENT', 'development')",
      "define('ENVIRONMENT', 'production')",
      $index,
    );
    file_put_contents($index_path, $index);
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/CodeIgniter-2.2.0';
  }
}
