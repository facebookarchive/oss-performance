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

final class Drupal8Target extends PerfTarget {
  public function __construct(
    private PerfOptions $options,
  ) {
  }

  protected function getSanityCheckString(): string {
    return 'Read more';
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
        __DIR__.'/drupal-8.0.0-beta10.tar.gz',
        $this->options->tempDir,
      );
      Utils::ExtractTar(
        __DIR__.'/demo-static.tar.bz2',
        $this->getSourceRoot().'/sites/default',
      );
    }

    Utils::ExtractTar(
      __DIR__.'/settings.tar.gz',
      $this->getSourceRoot().'/sites/default',
    );

    (new DatabaseInstaller($this->options))
      ->setDatabaseName('drupal_bench')
      ->setDumpFile(__DIR__.'/dbdump.sql.gz')
      ->installDatabase();
    $current_dir = __DIR__;
    chdir($this->getSourceRoot());
    shell_exec('find . -name *.html.twig | drush scr sites/default/setup.php');
    chdir($current_dir);
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/drupal-8.0.0-beta10';
  }
}
