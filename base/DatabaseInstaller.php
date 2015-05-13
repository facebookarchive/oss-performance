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

final class DatabaseInstaller {
  private ?string $databaseName;
  private ?string $dumpFile;
  private ?string $username;
  private ?string $password = null;

  public function __construct(private PerfOptions $options): void {
  }

  public function getUsername() : ?string {
    return $this->username ? $this->username : $this->databaseName;
  }

  public function getPassword() : ?string {
    return $this->password !== null ? $this->password : $this->databaseName;
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
      $this->checkMySQLConnectionLimit();
      return false;
    }

    $conn = mysql_connect('127.0.0.1', $db, $db);
    $db_selected = mysql_select_db($db, $conn);
    if ($conn === false || $db_selected === false) {
      $this->createMySQLDatabase();
    }
    $this->checkMySQLConnectionLimit();

    $sed = null;
    if ($this->options->forceInnodb) {
      $sed = 'sed s/MyISAM/InnoDB/g |';
    }

    shell_exec(
      Utils::EscapeCommand(Vector {
        $this->options->dumpIsCompressed ? 'zcat' : 'cat',
        $dump,
      }).
      '|'.
      $sed .
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

  private function getRootConnection(): resource {
    print "MySQL admin user (default is 'root'): ";
    $this->username = trim(fgets(STDIN)) ?: 'root';
    fprintf(STDERR, '%s', 'MySQL admin password: ');
    $this->password = trim(fgets(STDIN));
    $conn = mysql_connect('127.0.0.1', $this->username, $this->password);
    if ($conn === false) {
      throw new Exception(
        'Failed to connect: '.mysql_error()
      );
    }
    return $conn;
  }

  private function checkMySQLConnectionLimit(): void {
    $conn = mysql_connect(
      '127.0.0.1', $this->getUsername(), $this->getPassword()
    );
    if ($conn === false) {
      throw new Exception(
        'Failed to connect: '.mysql_error()
      );
    }
    $data = mysql_fetch_assoc(
      mysql_query(
        "SHOW variables WHERE Variable_name = 'max_connections'",
        $conn
      )
    );
    mysql_close($conn);
    if ($data['Value'] < 1000) {
      fprintf(
        STDERR,
        "Connection limit is too low - some benchmarks will have connection ".
        "errors. This can be fixed for you..\n"
      );
      $conn = $this->getRootConnection();
      mysql_query(
        'SET GLOBAL max_connections = 1000',
        $conn
      );
      mysql_close($conn);
    }
  }

  private function createMySQLDatabase(): void {
    $db = $this->databaseName;
    invariant($db !== null, 'Database must be specified');
    fprintf(
      STDERR,
      '%s',
      "Can't connect to database ".
      "(mysql -h 127.0.0.1 -p$db -u $db $db). This can be ".
      "fixed for you.\n"
    );
    $conn = $this->getRootConnection();
    $edb = mysql_real_escape_string($db);
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
      'GRANT ALL PRIVILEGES ON '.$edb.'.* TO "'.$edb.'"@"%" '.
      'IDENTIFIED BY "'.$edb.'"',
      $conn,
    );
    mysql_query(
      "GRANT ALL PRIVILEGES ON $edb.* TO '$edb'@127.0.0.1 ".
      "IDENTIFIED BY '$edb'",
      $conn,
    );
  }
}
