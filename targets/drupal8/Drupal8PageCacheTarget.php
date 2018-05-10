<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class Drupal8PageCacheTarget extends Drupal8Target {
  public function install(): void {
    parent::install();

    (new DatabaseInstaller($this->options))
      ->setDatabaseName('drupal_bench')
      ->setDumpFile(__DIR__.'/dbdump-pagecache.sql.gz')
      ->installDatabase();

    $this->drushPrep();
  }

}
