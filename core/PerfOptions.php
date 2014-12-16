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

final class PerfOptions {
  public bool $help;
  public bool $verbose;

  //
  // Exactly one of php5 or hhvm must be set with the path
  // to the corresponding executable.  The one that is set
  // determines what kind of cgi server is run.
  //
  public ?string $php5;
  public ?string $hhvm;

  public array $hhvmExtraArguments;

  public string $siege;
  public string $nginx;

  public bool $skipSanityCheck = false;
  public bool $skipVersionChecks = false;
  public bool $skipDatabaseInstall = false;
  public bool $dumpIsCompressed = true;
  public bool $traceSubProcess = false;
  public bool $noTimeLimit = false;
  
  //
  // All times are given in seconds, stored in a float.
  // For PHP code, the usleep timer is used, so fractional seconds work fine.
  //
  // For times that go into configuration files for 3rd party software,
  // such as nginx, times may be truncated to the nearest integer value,
  // in order to accomodate inflexibility in the 3rd party software.
  //
  public float $delayNginxStartup;
  public float $delayPhpStartup;
  public float $delayProcessLaunch;  // secs to wait after start process
  public float $delayCheckHealth;    // secs to wait before hit /check-health

  //
  // Maximum wait times, as for example given to file_get_contents
  // or the configuration file for nginx.  These times may be truncated
  // to the nearest integral second to accomodate the specific server.
  //
  public float $maxdelayUnfreeze;
  public float $maxdelayAdminRequest;
  public float $maxdelayNginxKeepAlive;
  public float $maxdelayNginxFastCGI;

  public bool $daemonOutputToFile = false;
  public string $tempDir;

  public bool $notBenchmarking = false;
  public int  $benchmarkConcurrency;

  private array $args;
  private Vector<string> $notBenchmarkingArgs = Vector { };

  public function __construct($argv) {
    $def = Vector {
      'help',

      'verbose',

      'php5:',
      'hhvm:',
      'siege:',
      'nginx:',

      'i-am-not-benchmarking',
      'concurrency:',

      'hhvm-extra-arguments:',

      'no-time-limit',

      'skip-sanity-check',
      'skip-version-checks',
      'skip-database-install',
      'trace',

      'delay-nginx-startup:',
      'delay-php-startup:',
      'delay-process-launch:',
      'delay-check-health:',

      'max-delay-unfreeze:',
      'max-delay-admin-request:',
      'max-delay-nginx-keepalive:',
      'max-delay-nginx-fastcgi:',

      'daemon-files',  // daemon output goes to files in the temp directory
      'temp-dir:',  // temp directory to use; if absent one in /tmp is made
    };
    $targets = $this->getTargetDefinitions()->keys();
    $def->addAll($targets);
    $o = getopt('', $def);

    $this->help = array_key_exists('help', $o);
    if ($this->help) {
      fprintf(
        STDERR,
        "Usage: %s \\\n".
        "  --<php5=/path/to/php-cgi|hhvm=/path/to/hhvm>\\\n".
        "  --<".implode('|',$targets).">\n".
        "\n".
        "Options:\n%s",
        $argv[0],
        implode('', $def->map($x ==> '  --'.$x."\n")),
      );
      exit(1);
    };
    $this->verbose = array_key_exists('verbose', $o);

    $this->php5 = hphp_array_idx($o, 'php5', null);
    $this->hhvm = hphp_array_idx($o, 'hhvm', null);

    $this->siege = hphp_array_idx($o, 'siege', 'siege');
    $this->nginx = hphp_array_idx($o, 'nginx', 'nginx');

    $this->traceSubProcess = array_key_exists('trace', $o);

    $this->notBenchmarking = array_key_exists('i-am-not-benchmarking', $o);
    $this->benchmarkConcurrency = hphp_array_idx($o, 'concurrency', 60);

    // If any arguments below here are given, then the "standard
    // semantics" have changed, and any results are potentially not
    // consistent with the benchmark standards for HHVM. You can only
    // use these arguments if you also give the -i-am-not-benchmarking
    // argument too.
    $this->args= $o;

    $this->skipSanityCheck = $this->getBool('skip-sanity-check');
    $this->skipVersionChecks = $this->getBool('skip-version-checks');
    $this->skipDatabaseInstall = $this->getBool('skip-database-install');
    $this->noTimeLimit = $this->getBool('no-time-limit');

    $this->hhvmExtraArguments = $this->getArray('hhvm-extra-arguments');
    $this->delayNginxStartup = $this->getFloat('delay-nginx-startup', 0.1);
    $this->delayPhpStartup = $this->getFloat('delay-php-startup', 1.0);
    $this->delayProcessLaunch = $this->getFloat('delay-process-launch', 0.0);
    $this->delayCheckHealth = $this->getFloat('delay-check-health', 1.0);
    $this->maxdelayUnfreeze = $this->getFloat('max-delay-unfreeze', 60.0);
    $this->maxdelayAdminRequest = $this->getFloat(
      'max-delay-admin-request',
      3.0
    );
    $this->maxdelayNginxKeepAlive = $this->getFloat(
      'max-delay-nginx-keep-alive',
      60.0
    );
    $this->maxdelayNginxFastCGI = $this->getFloat(
      'max-delay-nginx-fastcgi',
      60.0
    );

    $this->daemonOutputToFile = $this->getBool('daemon-files');

    $argTempDir = $this->getNullableString('temp-dir');

    if ($argTempDir === null) {
      $this->tempDir = tempnam('/tmp', 'hhvm-nginx');
      // Currently a file - change to a dir
      unlink($this->tempDir);
      mkdir($this->tempDir);
    } else {
      $this->tempDir = $argTempDir;
    }

    if ($this->notBenchmarkingArgs && !$this->notBenchmarking) {
      fprintf(
        STDERR,
        "These arguments are invalid without --i-am-not-benchmarking: %s\n",
        implode(' ', $this->notBenchmarkingArgs),
      );
      exit(1);
    }
  }

  private function getBool(string $name): bool {
    $value = array_key_exists($name, $this->args);
    if ($value) {
      $this->notBenchmarkingArgs[] = '--'.$name;
    }
    return $value;
  }

  private function getNullableString(string $name): ?string {
    if (!array_key_exists($name, $this->args)) {
      return null;
    }
    $this->notBenchmarkingArgs[] = '--'.$name;
    return $this->args[$name];
  }

  // getopt allows multiple instances of the same argument,
  // in which case $options[$index] is an array.
  // If only one instance is given, then getopt just uses a string.
  private function getArray(string $name): array<string> {
    if (array_key_exists($name, $this->args)) {
      $this->notBenchmarkingArgs[] = '--'.$name;
    } else {
      return array();
    }
    $option_value = hphp_array_idx($this->args, $name, array());
    if (is_array($option_value)) {
      return $option_value;
    } else {
      return array($option_value);
    }
  }

  private function getFloat(
    string $index,
    float $the_default,
  ) : float {
    if (array_key_exists($index, $this->args)) {
      $this->notBenchmarkingArgs[] = '--'.$index;
    }
    return (float)hphp_array_idx($this->args, $index, $the_default);
  }

  //
  // Return the name of a file that should collect stdout
  // from daemon executions.  Returning null means that
  // the daemon stdout should go to a pipe attached to this process.
  //
  public function daemonOutputFileName(string $daemonName): ?string {
    if ($this->daemonOutputToFile) {
      return (($this->tempDir === null) ? '/tmp' : $this->tempDir)
        . '/' . $daemonName . '.out';
    } else {
      return null;
    }
  }

  <<__Memoize>>
  public function getTarget(): PerfTarget {
    $multiple = false;
    $target = null;
    $def = $this->getTargetDefinitions();
    foreach ($def as $flag => $factory) {
      if (array_key_exists($flag, $this->args)) {
        if ($target === null) {
          $target = $factory();
        } else {
          $multiple = true;
        }
      }
    }
    if ($multiple || ($target === null)) {
      fprintf(
        STDERR,
        "You must specify a target with exactly one of the following:\n".
        implode('', $def->keys()->map($arg ==> '  --'.$arg."\n"))
      );
      exit(1);
    }
    return $target;
  }

  private function getTargetDefinitions(
  ): Map<string, (function(): PerfTarget)> {
    return Map {
      'codeigniter-hello-world' => () ==> new CodeIgniterTarget($this),
      'drupal7' => () ==> new Drupal7Target($this),
      'mediawiki' => () ==> new MediaWikiTarget($this),
      'laravel-hello-world' => () ==> new LaravelTarget($this),
      'sugarcrm-login-page' => () ==> new SugarCRMTarget($this),
      'toys-fibonacci' => () ==> new FibonacciTarget(),
      'toys-hello-world' => () ==> new HelloWorldTarget(),
      'wordpress' => () ==> new WordpressTarget($this),
      'magento1' => () ==> new Magento1Target($this)
    };
  }
}
