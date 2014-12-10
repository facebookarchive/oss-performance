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

final class Magento1Target extends PerfTarget {
  private DatabaseInstaller $installer;

  public function __construct(
    private PerfOptions $options
  ) {
    $options->dumpIsCompressed = false;
    $this->installer = new DatabaseInstaller($this->options);
    $this->installer->setDatabaseName($this->getDatabaseName());
  }

  private function getDatabaseName() : string {
    return 'magento_bench';
  }

  protected function getSanityCheckString(): string {
    return 'Madison Island';
  }

  private function getMagentoInstaller() : Mage_Install_Model_Installer_Console {
    require_once $this->getSourceRoot().'/app/Mage.php';
    $app = Mage::app('default');

    $installer = Mage::getSingleton('install/installer_console');
    $installer->init(Mage::app('default'));
    $installer->setArgs($this->getInstallerArgs());
    return $installer;
  }

  private function setPermissions() : void {
    foreach (array('media', 'var') as $dir) {
      shell_exec($this->safeCommand(Vector {
        'chmod',
        '-R',
        'o+w',
        $this->getSourceRoot().'/'.$dir 
      }));
    }
  }

  private function installSampleData() : bool {
    Utils::ExtractTar(
      __DIR__.'/magento1/magento-sample-data-1.9.0.0.tar.gz',
      $this->options->tempDir
    );

    foreach (array('skin', 'media') as $type) {
      shell_exec(implode(' ', array(
        'cp',
        '-Rf', $this->getSampleDataDirectory().'/'.$type.'/*',
        $this->getSourceRoot().'/'.$type
      )));
    }

    $created_database = $this->installer
      ->setDumpFile($this->getSampleDataDirectory().'/magento_sample_data_for_1.9.0.0.sql')
      ->installDatabase();
    if (!$created_database) {
      return false;
    }
    return true;
  }

  <<__Memoize>>
  private function getSampleDataDirectory() : string {
    return $this->options->tempDir.'/magento-sample-data-1.9.0.0';
  }

  public function install(): void {
    Utils::ExtractTar(
      __DIR__.'/magento1/magento-1.9.0.1.tar.gz',
      $this->options->tempDir,
    );
    if ($this->options->skipDatabaseInstall) {
      copy(
        __DIR__.'/magento1/local.xml',
        $this->getSourceRoot().'/app/etc/local.xml',
      );
      return;
    }

    if (!$this->installSampleData()) {
      throw new Exception('Could not install sample data.');
    }
    $this->setPermissions();

    $installer = $this->getMagentoInstaller();
    $installer->install();
    if ($installer->hasErrors()) {
      throw new Exception(sprintf("Installation failed: %s\n",
        implode(PHP_EOL, $installer->getErrors()))
      );
    }
  }

  private function getInstallerArgs() : array {
    $url = 'http://'.gethostname().':'.PerfSettings::HttpPort().'/';
    return array(
      'db_host'                    => '127.0.0.1',
      'db_name'                    => $this->getDatabaseName(),
      'db_user'                    => $this->installer->getUsername(),
      'db_pass'                    => $this->installer->getPassword(),
      'license_agreement_accepted' => 1,
      'locale'                     => 'en_US',
      'timezone'                   => 'UTC',
      'default_currency'           => 'USD',
      'url'                        => $url,
      'use_rewrites'               => 0,
      'use_secure'                 => 0,
      'secure_base_url'            => $url,
      'use_secure_admin'           => 0,
      'admin_firstname'            => 'Bench',
      'admin_lastname'             => 'Mark',
      'admin_email'                => 'bench@mark.com',
      'admin_username'             => 'benchmark',
      'admin_password'             => 'p@ssw0rd',
      'skip_url_validation'        => 1
    );
  }

  public function getSourceRoot(): string {
    return $this->options->tempDir.'/magento';
  }
}
