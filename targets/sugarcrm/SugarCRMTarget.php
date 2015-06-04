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

abstract class SugarCRMTarget extends PerfTarget {
  public function __construct(protected PerfOptions $options) {}

  public function install(): void {
    $src_dir = $this->options->srcDir;
    if ($src_dir) {
      Utils::CopyDirContents($src_dir, $this->getSourceRoot());
    } else {
      $pd = new PharData(__DIR__.'/SugarCE-6.5.20.zip');
      $pd->extractTo($this->options->tempDir);
    }

    copy(__DIR__.'/config.php', $this->getSourceRoot().'/config.php');

    if ($this->options->skipDatabaseInstall) {
      return;
    }

    (new DatabaseInstaller($this->options))
      ->setDatabaseName('sugarcrm')
      ->setDumpFile(__DIR__.'/dbdump.sql.gz')
      ->installDatabase();
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/SugarCE-Full-6.5.20';
  }
}
