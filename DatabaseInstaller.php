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

require_once('PerfOptions.php');
require_once('Utils.php');

final class DatabaseInstaller {
  private ?string $databaseName;
  private ?string $dumpFile;

  public function __construct(private PerfOptions $options): void {
  }

  public function setDatabaseName(string $database_name): this {
    $this->databaseName = $database_name;
    return $this;
  }

  public function setDumpFile(string $dump_file): this {
    $this->dumpFile = $dump_file;
    return $this;
  }

  public function installDatabase(): bool {
    $db = $this->databaseName;
    $dump = $this->dumpFile;
    invariant(
      $db !== null && $dump !== null,
      'database and dump must be specified'
    );
    if ($this->options->skipDatabaseInstall) {
      return false;
    }

    $conn = mysql_connect('127.0.0.1', $db, $db);
    $db_selected = mysql_select_db($db, $conn);
    if ($conn === false || $db_selected === false) {
      $this->createMySQLDatabase();
    }

    shell_exec(
      Utils::EscapeCommand(Vector {
        'zcat',
        $dump,
      }).
      '|'.
      Utils::EscapeCommand(Vector {
        'mysql',
        '-h', '127.0.0.1',
        $db,
        '-u', $db,
        '-p'.$db
      })
    );
    return true;
  }

  private function createMySQLDatabase(): void {
    $db = $this->databaseName;
    invariant($db !== null, 'Database must be specified');
    $edb = mysql_real_escape_string($db);
    fprintf(
      STDERR,
      '%s',
      "Can't connect to database ".
      "(mysql -h 127.0.0.1 -p$db -u $db $db). This can be ".
      "fixed for you.\nMySQL admin user (probably 'root'): ",
    );
    $username = trim(fgets(STDIN));
    if (!$username) {
      throw new Exception(
        'Invalid user - set up the wp_bench database and user manually.'
      );
    }
    fprintf(STDERR, '%s', 'MySQL admin password: ');
    $password = trim(fgets(STDIN));
    if (!$password) {
      throw new Exception(
        'Invalid password - set up the wp_bench database and user manually.'
      );
    }
    $conn = mysql_connect('127.0.0.1', $username, $password);
    if ($conn === false) {
      throw new Exception(
        'Failed to connect: '.mysql_error()
      );
    }
    mysql_query("DROP DATABASE IF EXISTS $edb", $conn);
    mysql_query("CREATE DATABASE $edb", $conn);
    /* In theory, either one of these works, with 127.0.0.1 being the minimal
     * one.
     * - do % so that if someone debugs with localhost, hostname, or ::1 (IPv6)
     *   it works as expectedd
     * - do 127.0.0.1 as well, just in case there's a pre-existing incompatible
     *   grant
     */
    mysql_query(
      'GRANT ALL PRIVILEGES ON '.$edb.'.* TO '.$edb.'@"%" '.
      'IDENTIFIED BY '.$edb,
      $conn,
    );
    mysql_query(
      "GRANT ALL PRIVILEGES ON $edb.* TO $edb@127.0.0.1 ".
      "IDENTIFIED BY $edb",
      $conn,
    );
  }
}
