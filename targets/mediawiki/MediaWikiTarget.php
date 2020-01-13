<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class MediaWikiTarget extends PerfTarget {

  const MEDIAWIKI_VERSION = 'mediawiki-1.28.0';

  public function __construct(private PerfOptions $options) {}

  protected function getSanityCheckString(): string {
    return 'Obama';
  }

  private function replaceInFile(string $fileName, string $search, string $replace) {
    $file = $this->getSourceRoot().'/'.$fileName;
    $file_contents = file_get_contents($file);
    $file_contents = str_replace($search, $replace, $file_contents);
    file_put_contents($file, $file_contents);
  }

  public function install(): void {
    $src_dir = $this->options->srcDir;
    if ($src_dir) {
      Utils::CopyDirContents($src_dir, $this->getSourceRoot());
    } else {
      Utils::ExtractTar(
        __DIR__.'/'.self::MEDIAWIKI_VERSION.'.tar.gz',
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
    copy(__DIR__.'/LocalSettings.php', $this->getSourceRoot().'/LocalSettings.php');

    $this->replaceInFile('LocalSettings.php', '__DB_HOST__', $this->options->dbHost);

    file_put_contents(
      $this->getSourceRoot().'/LocalSettings.php',
      '$wgCacheDirectory="'.$cache_dir.'";'."\n",
      FILE_APPEND,
    );
    if ($this->options->useMemcached) {
      copy(__DIR__.'/Memcached.php', $this->getSourceRoot().'/Memcached.php');
      $this->replaceInFile('Memcached.php', '__MEMCACHED_HOST__', '127.0.0.1');
      $this->replaceInFile('Memcached.php', '__MEMCACHED_PORT__', (string) $this->options->memcachedPort);
      file_put_contents(
        $this->getSourceRoot().'/LocalSettings.php',
        'require_once "'.$this->getSourceRoot().'/Memcached.php";'."\n",
        FILE_APPEND,
      );
    }
  }

  <<__Override>>
  public function postInstall(): void {
    Utils::RunCommand(
      Vector {
        PHP_BINARY,
        $this->getSourceRoot().'/maintenance/rebuildLocalisationCache.php',
        '--lang=en',
      },
    );
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/'.self::MEDIAWIKI_VERSION;
  }

  <<__Override>>
  public function supportsMemcached(): bool {
    return true;
  }
}
