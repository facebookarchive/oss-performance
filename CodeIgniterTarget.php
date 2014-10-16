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

require_once('PerfTarget.php');

final class CodeIgniterTarget extends PerfTarget {
  public function __construct(
    private PerfOptions $options,
  ) {
  }

  protected function getSanityCheckString(): string {
    return
      'The page you are looking at is being generated '.
      'dynamically by CodeIgniter.';
  }

  public function install(): void {
    shell_exec($this->safeCommand(Vector {
      'tar',
      '-C', $this->options->tempDir,
      '-zxf',
      __DIR__.'/codeigniter/CodeIgniter-2.2.0.tar.gz'
    }));

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
