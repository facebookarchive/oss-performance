<?hh
/*
 *  Copyright (c) 2017, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */
final class MemcachedDaemon extends Process {

  private int $maxMemory = 2048;

  public function __construct(
    private PerfOptions $options,
    private PerfTarget $target,
  ) {
    parent::__construct($this->options->memcached);
  }

  public function start(): void {
    parent::startWorker(
      $this->options->daemonOutputFileName('memcached'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
  }

  public function getNumThreads(): int {
    $output = [];
    $ret = -1;
    if ($this->options->memcachedThreads != 0) {
        return $this->options->memcachedThreads;
    }

    exec('nproc', &$output, &$ret);
    if ($ret != 0) {
       invariant_violation('%s', 'Execution of nproc failed');
       exit(1);
    }
    $numProcs = (int)($output[0]);

    // for small number of cores, use the default, which is 4;
    // otherwise, we probably need more
    if ($numProcs <= 8)
        return 4;

    return 32;
  }

  <<__Override>>
  protected function getPidFilePath(): string {
    return $this->options->tempDir.'/memcached.pid';
  }

  <<__Override>>
  protected function getArguments(): Vector<string> {
    if ($this->options->cpuBind) {
      $this->cpuRange = $this->options->helperProcessors;
    }
    return Vector {
      '-m',
      (string) $this->maxMemory,
      '-l',
      '127.0.0.1',
      '-t',
      (string) $this->getNumThreads(),
      '-p',
      (string) $this->options->memcachedPort,
      '-P', # pid file
      $this->getPidFilePath()
    };
  }
}
