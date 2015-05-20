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

    // Put it inside the source root so that if we're generating PHP files and
    // we're in repo-auth mode, the generated files end up in the repo
    $cache_dir = $this->getSourceRoot().'/mw-cache';
    mkdir($cache_dir);

    file_put_contents(
      $this->getSourceRoot().'/LocalSettings.php',
      '$wgCacheDirectory="'.$cache_dir.'";'.
      // Default behavior is to do a MySQL query *for each translatable string
      // on every page view*. This is just insane.
      '$wgLocalisationCacheConf["store"] = "file";'.
      // Default behavior is to maintain view counts in MySQL. Any real
      // large-scale deployment should be using a more scalable solution such
      // as log files or Google Analytics
      '$wgDisableCounters = true;',
      FILE_APPEND
    );
  }

  <<__Override>>
  public function postInstall(): void {
    Utils::RunCommand(Vector {
      PHP_BINARY,
      $this->getSourceRoot().'/maintenance/rebuildLocalisationCache.php',
      '--lang=en',
    });
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/mediawiki';
  }
}
