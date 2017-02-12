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
  // Exactly one of php or hhvm must be set with the path
  // to the corresponding executable.  The one that is set
  // determines what kind of cgi server is run.
  //
  public ?string $php;
  public ?string $hhvm;

  //
  // setUpTest and tearDownTest are called before and after each
  // individual invocation of the $php or $hhvm
  //
  public ?string $setUpTest;
  public ?string $tearDownTest;

  public array $hhvmExtraArguments;
  public array $phpExtraArguments;

  public int $phpFCGIChildren;

  public string $siege;
  public string $nginx;

  public bool $forceInnodb = false;
  public bool $skipSanityCheck = false;
  public bool $skipWarmUp = false;
  public bool $skipVersionChecks = false;
  public bool $skipDatabaseInstall = false;
  public bool $dumpIsCompressed = true;
  public bool $traceSubProcess = false;
  public bool $noTimeLimit = false;

  // Pause once benchmarking is complete to allow for manual inspection of the
  // HHVM or PHP process.
  public bool $waitAtEnd = false;
  // Pause after the warmup is completed to get relevant data when profiling
  public bool $waitAfterWarmup = false;

  //
  // HHVM specific options to enable RepoAuthoritative mode and the static
  // content cache, as well as selecting the PCRE caching mode.
  //
  public bool $precompile = false;
  public bool $filecache = false;
  public ?string $pcreCache;
  public ?int $pcreSize;
  public ?int $pcreExpire;
  public bool $allVolatile = false;
  public bool $interpPseudomains = false;
  public bool $proxygen = false;

  //
  // HHVM specific options for generating performance data and profiling
  // information.
  //
  public ?string $tcprint = null;
  public bool $tcAlltrans = false;
  public bool $tcToptrans = false;
  public bool $tcTopfuncs = false;
  public bool $pcredump = false;
  public bool $profBC = false;

  public bool $applyPatches = false;

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
  public float $delayProcessLaunch; // secs to wait after start process
  public float $delayCheckHealth; // secs to wait before hit /check-health

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
  public ?string $srcDir;

  public ?string $scriptBeforeWarmup;
  public ?string $scriptAfterWarmup;
  public ?string $scriptAfterBenchmark;

  public bool $notBenchmarking = false;

  private array $args;
  private Vector<string> $notBenchmarkingArgs = Vector {};

  public function __construct(Vector<string> $argv) {
    $def = Vector {
      'help',
      'verbose',
      'php:',
      'hhvm:',
      'siege:',
      'nginx:',
      'wait-at-end',
      'wait-after-warmup',
      'no-proxygen',
      'no-repo-auth',
      'no-file-cache',
      'pcre-cache:',
      'pcre-cache-expire:',
      'pcre-cache-size:',
      'all-volatile',
      'interp-pseudomains',
      'apply-patches',
      'force-innodb',
      'fbcode::',
      'tcprint::',
      'dump-top-trans',
      'dump-top-funcs',
      'dump-all-trans',
      'dump-pcre-cache',
      'profBC',
      'setUpTest:',
      'tearDownTest:',
      'i-am-not-benchmarking',
      'hhvm-extra-arguments:',
      'php-extra-arguments:',
      'php-fcgi-children:',
      'no-time-limit',
      'skip-sanity-check',
      'skip-warmup',
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
      'exec-before-warmup:',
      'exec-after-warmup:',
      'exec-after-benchmark:',
      'daemon-files', // daemon output goes to files in the temp directory
      'temp-dir:', // temp directory to use; if absent one in /tmp is made
      'src-dir:', // location for source to copy into tmp dir instead of ZIP
    };
    $targets = $this->getTargetDefinitions()->keys();
    $def->addAll($targets);

    $original_argv = $GLOBALS['argv'];
    $GLOBALS['argv'] = $argv;
    $o = getopt('', $def);
    $GLOBALS['argv'] = $original_argv;

    $this->help = array_key_exists('help', $o);
    if ($this->help) {
      fprintf(
        STDERR,
        "Usage: %s \\\n".
        "  --<php=/path/to/php-cgi|hhvm=/path/to/hhvm>\\\n".
        "  --<".
        implode('|', $targets).
        ">\n".
        "\n".
        "Options:\n%s",
        $argv[0],
        implode('', $def->map($x ==> '  --'.$x."\n")),
      );
      exit(1);
    }
    ;
    $this->verbose = array_key_exists('verbose', $o);

    $this->php = hphp_array_idx($o, 'php', null);
    $this->hhvm = hphp_array_idx($o, 'hhvm', null);

    $this->setUpTest = hphp_array_idx($o, 'setUpTest', null);
    $this->tearDownTest = hphp_array_idx($o, 'tearDownTest', null);

    $this->siege = hphp_array_idx($o, 'siege', 'siege');
    $this->nginx = hphp_array_idx($o, 'nginx', 'nginx');

    $isFacebook = array_key_exists('fbcode', $o);
    $fbcode = "";
    if ($isFacebook) {
      $val = hphp_array_idx($o, 'fbcode', false);
      if (is_string($val) && $val !== '') {
        $fbcode = $val;
      } else {
        $fbcode = getenv('HOME').'/fbcode';
      }
      $this->forceInnodb = true;
    }

    $this->notBenchmarking = array_key_exists('i-am-not-benchmarking', $o);

    // If any arguments below here are given, then the "standard
    // semantics" have changed, and any results are potentially not
    // consistent with the benchmark standards for HHVM. You can only
    // use these arguments if you also give the -i-am-not-benchmarking
    // argument too.
    $this->args = $o;

    $this->skipSanityCheck = $this->getBool('skip-sanity-check');
    $this->skipWarmUp = $this->getBool('skip-warmup');
    $this->waitAfterWarmup = $this->getBool('wait-after-warmup');
    $this->skipVersionChecks = $this->getBool('skip-version-checks');
    $this->skipDatabaseInstall = $this->getBool('skip-database-install');
    $this->noTimeLimit = $this->getBool('no-time-limit');
    $this->waitAtEnd = $this->getBool('wait-at-end');
    $this->proxygen = !$this->getBool('no-proxygen');
    $this->applyPatches = $this->getBool('apply-patches');

    $this->precompile = !$this->getBool('no-repo-auth');
    $this->filecache = $this->precompile && !$this->getBool('no-file-cache');
    $this->pcreCache = $this->getNullableString('pcre-cache');
    $this->pcreSize = $this->getNullableInt('pcre-cache-size');
    $this->pcreExpire = $this->getNullableInt('pcre-cache-expire');
    $this->allVolatile = $this->getBool('all-volatile');
    $this->interpPseudomains = $this->getBool('interp-pseudomains');

    $this->scriptBeforeWarmup = $this->getNullableString('exec-before-warmup');
    $this->scriptAfterWarmup = $this->getNullableString('exec-after-warmup');
    $this->scriptAfterBenchmark = $this->getNullableString('exec-after-benchmark');

    if ($this->getBool('tcprint')) {
      $tcprint = hphp_array_idx($o, 'tcprint', null);
      if (is_string($tcprint) && $tcprint !== '') {
        $this->tcprint = $tcprint;
      } else if ($isFacebook) {
        $this->tcprint =
          $fbcode.'/_bin/hphp/facebook/tools/tc-print/tc-print';
      }
    }
    $this->tcAlltrans = $this->getBool('dump-all-trans');
    $this->tcToptrans = $this->getBool('dump-top-trans');
    $this->tcTopfuncs = $this->getBool('dump-top-funcs');
    $this->pcredump = $this->getBool('dump-pcre-cache');
    $this->profBC = $this->getBool('profBC');
    $this->forceInnodb = $isFacebook || $this->getBool('force-innodb');

    if ($this->tcprint !== null && !$this->tcTopfuncs && !$this->tcToptrans) {
      $this->tcAlltrans = true;
    }

    if ($isFacebook && $this->php === null && $this->hhvm === null) {
      $this->hhvm = $fbcode.'/_bin/hphp/hhvm/hhvm';
    }

    $this->traceSubProcess = $this->getBool('trace');

    $this->hhvmExtraArguments = $this->getArray('hhvm-extra-arguments');
    $this->phpExtraArguments = $this->getArray('php-extra-arguments');

    $this->phpFCGIChildren = $this->getInt('php-fcgi-children', 100);
    $this->delayNginxStartup = $this->getFloat('delay-nginx-startup', 0.1);
    $this->delayPhpStartup = $this->getFloat('delay-php-startup', 1.0);
    $this->delayProcessLaunch = $this->getFloat('delay-process-launch', 0.0);
    $this->delayCheckHealth = $this->getFloat('delay-check-health', 1.0);
    $this->maxdelayUnfreeze = $this->getFloat('max-delay-unfreeze', 60.0);
    $this->maxdelayAdminRequest =
      $this->getFloat('max-delay-admin-request', 3.0);
    $this->maxdelayNginxKeepAlive =
      $this->getFloat('max-delay-nginx-keep-alive', 60.0);
    $this->maxdelayNginxFastCGI =
      $this->getFloat('max-delay-nginx-fastcgi', 60.0);

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

    $this->srcDir = $this->getNullableString('src-dir');

  }

  public function validate() {
    if ($this->php) {
      $this->precompile = false;
      $this->proxygen = false;
      $this->filecache = false;
    }
    if ($this->notBenchmarkingArgs && !$this->notBenchmarking) {
      $message = sprintf(
        "These arguments are invalid without --i-am-not-benchmarking: %s",
        implode(' ', $this->notBenchmarkingArgs),
      );
      if (getenv("HHVM_OSS_PERF_BE_LENIENT")) {
        fprintf(STDERR, "*** WARNING ***\n%s\n", $message);
        $this->notBenchmarking = true;
      } else {
        invariant_violation('%s', $message);
        exit(1);
      }
    }
    if ($this->php === null && $this->hhvm === null) {
      invariant_violation(
        'Either --php=/path/to/php-cgi or --hhvm=/path/to/hhvm '.
        "must be specified",
      );
    }
    $engine = $this->php !== null ? $this->php : $this->hhvm;
    invariant(
      shell_exec('which '.escapeshellarg($engine)) !== null ||
      is_executable($engine),
      'Invalid engine: %s',
      $engine,
    );
    invariant(
      shell_exec('which '.escapeshellarg($this->siege)) !== null ||
      is_executable($this->siege),
      'Could not find siege',
    );

    $tcprint = $this->tcprint;
    if ($tcprint !== null) {
      invariant(
        $tcprint !== '' &&
        (shell_exec('which '.escapeshellarg($tcprint)) !== null ||
         is_executable($tcprint)),
        'Invalid tcprint: %s',
        $tcprint,
      );
    }

    if ($this->tcAlltrans || $this->tcToptrans || $this->tcTopfuncs) {
      invariant(
        $tcprint !== null,
        '--tcprint=/path/to/tc-print must be specified if --tc-all-trans, '.
        '--tc-top-trans, or --tc-top-funcs are specified',
      );
    }

    if ($this->pcreCache !== null || $this->pcreSize || $this->pcreExpire) {
      invariant(
        $this->hhvm !== null,
        'The PCRE caching scheme can only be tuned for hhvm',
      );
    }

    if ($this->precompile) {
      invariant(
        $this->hhvm !== null,
        'Only hhvm can be used with --repo-auth',
      );
    }

    SystemChecks::CheckAll($this);

    // Validates that one was defined
    $this->getTarget();
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

  private function getInt(string $index, int $the_default): int {
    if (array_key_exists($index, $this->args)) {
      $this->notBenchmarkingArgs[] = '--'.$index;
    }
    return (int) hphp_array_idx($this->args, $index, $the_default);
  }

  private function getNullableInt(string $name): ?int {
    if (!array_key_exists($name, $this->args)) {
      return null;
    }
    $this->notBenchmarkingArgs[] = '--'.$name;
    return $this->args[$name];
  }

  private function getFloat(string $index, float $the_default): float {
    if (array_key_exists($index, $this->args)) {
      $this->notBenchmarkingArgs[] = '--'.$index;
    }
    return (float) hphp_array_idx($this->args, $index, $the_default);
  }

  //
  // Return the name of a file that should collect stdout
  // from daemon executions.  Returning null means that
  // the daemon stdout should go to a pipe attached to this process.
  //
  public function daemonOutputFileName(string $daemonName): ?string {
    if ($this->daemonOutputToFile) {
      return
        (($this->tempDir === null) ? '/tmp' : $this->tempDir).
        '/'.
        $daemonName.
        '.out';
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
        implode('', $def->keys()->map($arg ==> '  --'.$arg."\n")),
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
      'drupal8-page-cache' => () ==> new Drupal8PageCacheTarget($this),
      'drupal8-no-cache' => () ==> new Drupal8NoCacheTarget($this),
      'mediawiki' => () ==> new MediaWikiTarget($this),
      'laravel4-hello-world' => () ==> new Laravel4Target($this),
      'laravel5-hello-world' => () ==> new Laravel5Target($this),
      'sugarcrm-login-page' => () ==> new SugarCRMLoginPageTarget($this),
      'sugarcrm-home-page' => () ==> new SugarCRMHomePageTarget($this),
      'toys-fibonacci' => () ==> new FibonacciTarget(),
      'toys-hello-world' => () ==> new HelloWorldTarget(),
      'wordpress' => () ==> new WordpressTarget($this),
      'magento1' => () ==> new Magento1Target($this),
    };
  }
}
