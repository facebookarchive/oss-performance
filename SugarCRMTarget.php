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

require_once('DatabaseInstaller.php');
require_once('PerfTarget.php');

final class SugarCRMTarget extends PerfTarget {
  public function __construct(
    private PerfOptions $options,
  ) {
  }

  protected function getSanityCheckString(): string {
    return 'User Name:';
  }

  public function install(): void {
    Utils::ExtractTar(
      __DIR__.'/sugarcrm/sugarcrm_dev-6.5.16.tar.gz',
      $this->options->tempDir,
    );

    copy(
      __DIR__.'/sugarcrm/config.php',
      $this->getSourceRoot().'/config.php',
    );

    if ($this->options->skipDatabaseInstall) {
      return;
    }

    (new DatabaseInstaller($this->options))
      ->setDatabaseName('sugarcrm')
      ->setDumpFile(__DIR__.'/sugarcrm/dbdump.sql.gz')
      ->installDatabase();
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/sugarcrm_dev-6.5.16';
  }

  // See PerfTarget::ignorePath() for documentation
  public function ignorePath(string $path): bool {
    // Users don't actually request this
    if (strstr($path, 'wp-cron.php')) {
      return true;
    }
    return false;
  }
}
