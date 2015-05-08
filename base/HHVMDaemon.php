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

final class HHVMDaemon extends PHPEngine {
  private PerfTarget $target;
  private int $mainPort;
  private int $adminPort;
  private string $serverType;

  public function __construct(
    private PerfOptions $options,
  ) {
    $this->target = $options->getTarget();
    parent::__construct((string) $options->hhvm);

    if ($options->proxygen) {
      $this->serverType = 'proxygen';
      $this->mainPort = PerfSettings::HttpPort();
      $this->adminPort = PerfSettings::HttpAdminPort();
    } else {
      $this->serverType = 'fastcgi';
      $this->mainPort = PerfSettings::FastCGIPort();
      $this->adminPort = PerfSettings::FastCGIAdminPort();
    }

    $output = [];
    $check_command = implode(
      ' ',
      (Vector {
          $options->hhvm,
          '-v', 'Eval.Jit=1',
          __DIR__.'/hhvm_config_check.php',
      })->map($x ==> escapeshellarg($x))
    );
    if ($options->traceSubProcess) {
      fprintf(STDERR, "%s\n", $check_command);
    }
    exec($check_command, $output);
    $checks = json_decode(implode("\n", $output), /* as array = */ true);
    invariant($checks, 'Got invalid output from hhvm_config_check.php');
    if (array_key_exists('HHVM_VERSION', $checks)) {
      $version = $checks['HHVM_VERSION'];
      if (version_compare($version, '3.4.0') === -1) {
        fprintf(
          STDERR,
          'WARNING: Unable to confirm HHVM is built correctly. This is '.
          'supported in 3.4.0-dev or later - detected %s. Please make sure '.
          'that your HHVM build is a release build, and is built against '.
          "libpcre with JIT support.\n",
          $version
        );
        sleep(2);
        return;
      }
    }
    BuildChecker::Check(
      $options,
      (string) $options->hhvm,
      $checks,
      Set { 'HHVM_VERSION' },
    );
  }
  
  protected function getTarget(): PerfTarget {
    return $this->target;
  }

  <<__Override>>
  protected function getArguments(): Vector<string> {
    $args = Vector {
      '-m', 'server',
      '-p', (string) $this->mainPort,
      '-v', 'Server.Type='.$this->serverType,
      '-v', 'Server.DefaultDocument=index.php',
      '-v', 'Server.SourceRoot='.$this->target->getSourceRoot(),
      '-v', 'Eval.Jit=1',
      '-v', 'AdminServer.Port='.$this->adminPort,
      '-c', OSS_PERFORMANCE_ROOT.'/conf/php.ini',
    };
    if ($this->options->pcreCache) {
      $args->addAll(Vector {
        '-v', 'Eval.PCRECacheType='.$this->options->pcreCache
      });
    }
    if ($this->options->pcreSize) {
      $args->addAll(Vector {
        '-v', 'Eval.PCRETableSize='.$this->options->pcreSize
      });
    }
    if ($this->options->pcreExpire) {
      $args->addAll(Vector {
        '-v', 'Eval.PCREExpireInterval='.$this->options->pcreExpire
      });
    }
    if (count($this->options->hhvmExtraArguments) > 0) {
      $args->addAll($this->options->hhvmExtraArguments);
    }
    if ($this->options->precompile) {
      $bcRepo = $this->options->tempDir . '/hhvm.hhbc';
      $args->add('-v'); $args->add('Repo.Authoritative=true');
      $args->add('-v'); $args->add('Repo.Central.Path=' . $bcRepo);
    }
    if ($this->options->filecache) {
      $sourceRoot = $this->getTarget()->getSourceRoot();
      $staticContent = $this->options->tempDir . '/static.content';
      $args->add('-v'); $args->add('Server.FileCache=' . $staticContent);
      $args->add('-v'); $args->add('Server.SourceRoot=' . $sourceRoot);
    }
    if ($this->options->tcprint !== null) {
      $args->add('-v'); $args->add('Eval.JitTransCounters=true');
      $args->add('-v'); $args->add('Eval.DumpTC=true');
    }
    if ($this->options->profBC) {
      $args->add('-v'); $args->add('Eval.ProfileBC=true');
    }
    if ($this->options->interpPseudomains) {
      $args->add('-v'); $args->add('Eval.JitPseudomain=false');
    }
    if ($this->options->allVolatile) {
      $args->add('-v'); $args->add('Eval.AllVolatile=true');
    }
    return $args;
  }

  <<__Override>>
  public function start(): void {
    if ($this->options->precompile) {
      $sourceRoot = $this->getTarget()->getSourceRoot();
      $fileList = $this->options->tempDir . '/files.txt';
      $hhvm = $this->options->hhvm;
      invariant(!is_null($hhvm), "Must have hhvm path");
      $args = Vector {
        $hhvm,
        '--hphp',
        '--input-list', $fileList,
        '--target',     'hhbc',
        '--output-dir', $this->options->tempDir,
        '--input-dir',  $sourceRoot,
        '--module',     $sourceRoot,
        '-l3',
        '-k1',
      };

      if ($this->options->allVolatile) {
        $args->add('-v'); $args->add('AllVolatile=true');
      }

      invariant(is_dir($sourceRoot), 'Could not find valid source root');

      $files = Vector{};
      $dir_iter = new RecursiveDirectoryIterator($sourceRoot);
      $iter     = new RecursiveIteratorIterator($dir_iter);
      foreach ($iter as $path => $_) {
        if (is_file($path)) {
          $files->add(ltrim(substr($path, strlen($sourceRoot)), '/'));
        }
      }
      file_put_contents($fileList, implode("\n", $files));

      $bcRepo = $this->options->tempDir . '/hhvm.hhbc';
      $staticContent = $this->options->tempDir . '/static.content';
      if (file_exists($bcRepo)) {
        unlink($bcRepo);
      }

      if ($this->options->filecache) {
        if (file_exists($staticContent)) {
          unlink($staticContent);
        }
        $args->add('--file-cache');
        $args->add($this->options->tempDir . '/static.content');
      }

      Utils::RunCommand($args);

      invariant(file_exists($bcRepo), 'Failed to create bytecode repo');
      invariant(
        !$this->options->filecache || file_exists($staticContent),
        'Failed to create static content cache'
      );
    }

    if ($this->options->pcredump) {
      if (file_exists('/tmp/pcre_cache')) {
        unlink('/tmp/pcre_cache');
      }
    }

    parent::startWorker(
      $this->options->daemonOutputFileName('hhvm'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
    invariant($this->isRunning(), 'Failed to start HHVM');
    for ($i = 0; $i < 10; ++$i) {
      Process::sleepSeconds($this->options->delayCheckHealth);
      $health = $this->adminRequest('/check-health', true);
      if ($health) {
        if ($health === "failure") {
          continue;
        }
        $health = json_decode($health, /* assoc array = */ true);
        if (array_key_exists('tc-size', $health) &&
          ($health['tc-size'] > 0 || $health['tc-hotsize'] > 0)) {
          return;
        }
      }
    }
    // Whoops...
    $this->stop();
  }

  public function stop(): void {
    try {
      $health = $this->adminRequest('/check-health');
      if ($health && json_decode($health)) {
        $this->adminRequest('/stop');
        invariant(!$this->isRunning(), 'Failed to stop HHVM');
      }
    } catch (Exception $e) { }

    parent::stop();
  }

  public function writeStats(): void {
    $tcprint = $this->options->tcprint;
    if ($tcprint) {
      $conf = $this->options->tempDir . '/conf.hdf';
      $args = Vector {};
      $hdf  = false;
      foreach ($this->getArguments() as $arg) {
        if ($hdf) $args->add($arg);
        $hdf = $arg === '-v';
      }
      $confData = implode("\n", $args);

      file_put_contents($conf, $confData);
      $args = Vector { $tcprint, '-c', $conf };

      $result = $this->adminRequest('/vm-dump-tc');
      invariant(
        $result === 'Done' && file_exists('/tmp/tc_dump_a'),
        'Failed to dump TC'
      );

      if ($this->options->tcAlltrans) {
        $alltrans = Utils::RunCommand($args);
        file_put_contents('tc-all', $alltrans);
      }

      if ($this->options->tcToptrans) {
        $new_args = new Vector($args);
        $new_args->add('-t'); $new_args->add('100');
        $toptrans = Utils::RunCommand($new_args);
        file_put_contents('tc-top-trans', $toptrans);
      }

      if ($this->options->tcTopfuncs) {
        $new_args = new Vector($args);
        $new_args->add('-T'); $new_args->add('100');
        $topfuncs = Utils::RunCommand($new_args);
        file_put_contents('tc-top-funcs', $topfuncs);
      }
    }

    if ($this->options->pcredump) {
      $result = $this->adminRequest('/dump-pcre-cache');
      invariant(
        $result === "OK\n" && file_exists('/tmp/pcre_cache'),
        'Failed to dump PCRE cache'
      );

      // move dump to CWD
      rename('/tmp/pcre_cache', getcwd() . '/pcre_cache');
    }
  }

  protected function adminRequest(
    string $path,
    bool $allowFailures = true
  ): string {
    $url = 'http://localhost:'.PerfSettings::HttpAdminPort().$path;
    $ctx = stream_context_create(
      ['http' => ['timeout' => $this->options->maxdelayAdminRequest]]
    );
    //
    // TODO: it would be nice to suppress
    // Warning messages from file_get_contents
    // in the event that the connection can't even be made.
    //
    $result = file_get_contents(
      $url,
      /* include path = */ false,
      $ctx);
    if ($result !== false) {
      return $result;
    }
    if ($allowFailures) {
      return "failure";
    } else {
      invariant($result !== false, 'Admin request failed');
      return $result;
    }
  }

  protected function getEnvironmentVariables(): Map<string, string> {
    return Map {
      'OSS_PERF_TARGET' => (string) $this->target,
    };
  }

  public function __toString(): string {
    return (string) $this->options->hhvm;
  }
}
