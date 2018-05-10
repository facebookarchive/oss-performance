<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

abstract class Drupal8Target extends PerfTarget {
  public function __construct(protected PerfOptions $options) {}

  protected function getSanityCheckString(): string {
    return 'Read more';
  }

  public function install(): void {
    $src_dir = $this->options->srcDir;
    if ($src_dir) {
      Utils::CopyDirContents($src_dir, $this->getSourceRoot());
    } else {
      # Extract Drupal core.
      Utils::ExtractTar(
        __DIR__.'/drupal-8.0.0-beta11.tar.gz',
        $this->options->tempDir,
      );
      # Extract Drush and its vendor dir.
      Utils::ExtractTar(
        __DIR__.'/drush-b4c0683.tar.bz2',
        $this->options->tempDir,
      );
      Utils::ExtractTar(
        __DIR__.'/drush-b4c0683-vendor.tar.bz2',
        $this->options->tempDir,
      );
      # Extract static files.
      Utils::ExtractTar(
        __DIR__.'/demo-static.tar.bz2',
        $this->getSourceRoot().'/sites/default',
      );
    }
    # Settings files and our Twig template setup script.
    copy(
      __DIR__.'/settings/settings.php',
      $this->getSourceRoot().'/sites/default/settings.php',
    );
    $file = $this->getSourceRoot().'/sites/default/settings.php';
    $file_contents = file_get_contents($file);
    $file_contents = str_replace('__DB_HOST__', $this->options->dbHost, $file_contents );
    file_put_contents($file, $file_contents);
    copy(
      __DIR__.'/settings/setup.php',
      $this->getSourceRoot().'/sites/default/setup.php',
    );
    copy(
      __DIR__.'/settings/services.yml',
      $this->getSourceRoot().'/sites/default/services.yml',
    );

    # Installing the database is left to the child targets.
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/drupal-8.0.0-beta11';
  }

  public function drushPrep(): void {
    // For repo.auth mode to work, we need Drush to run a setup script that
    // populates Twig template files.
    $hhvm = shell_exec('which hhvm');
    if ($hhvm) {
      putenv("DRUSH_PHP=$hhvm");
    }
    $drush = $this->options->tempDir.'/drush/drush';
    $current = getcwd();
    chdir($this->getSourceRoot());

    // Rebuild Drupal's cache to clear out stale filesystem entries.
    shell_exec($drush.' cr');
    // Try to pre-generate all Twig templates.
    shell_exec(
      'find . -name *.html.twig | '.
      $drush.
      ' scr sites/default/setup.php 2>&1',
    );
    chdir($current);
  }

}
