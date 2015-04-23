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

final class MediaWikiTarget extends PerfTarget {
  public function __construct(
    private PerfOptions $options,
  ) {
  }

  protected function getSanityCheckString(): string {
    return 'Obama';
  }

  public function install(): void {
    $src_dir = $this->options->srcDir;
    if ($src_dir) {
      Utils::CopyDirContents(
        $src_dir,
        $this->getSourceRoot(),
      );
    } else {
      Utils::ExtractTar(
        __DIR__.'/mediawiki-1.24.0.tar.gz',
        $this->options->tempDir,
      );
    }

    (new DatabaseInstaller($this->options))
      ->setDatabaseName('mw_bench')
      ->setDumpFile(__DIR__.'/mw_bench.sql.gz')
      ->installDatabase();
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/mediawiki';
  }
}
