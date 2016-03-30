<?hh
/*
 *  Copyright (c) 2016, Intel Corporation.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

final class FPMDaemon extends PHPEngine {
  private PerfTarget $target;

  public function __construct(private PerfOptions $options) {
    $this->target = $options->getTarget();
    parent::__construct((string) $options->fpm);

    // TOOD nice to have: check whether opcache is loaded
  }

  public function start(): void {
    parent::startWorker(
      $this->options->daemonOutputFileName('fpm'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
  }

  protected function getArguments(): Vector<string> {
    $args = Vector {
      '-y',
      OSS_PERFORMANCE_ROOT.'/conf/php-fpm.conf',
      '-c',
      OSS_PERFORMANCE_ROOT.'/conf/php.ini',
      '--pid',
      $this->options->tempDir.'/php-fpm.pid',
    };

    if (count($this->options->phpExtraArguments) > 0) {
      $args->addAll($this->options->phpExtraArguments);
    }
    return $args;
  }

  protected function getEnvironmentVariables(): Map<string, string> {
    return Map {
      'OSS_PERF_TARGET' => (string) $this->target,
    };
  }

  public function __toString(): string {
    return (string) $this->options->fpm;
  }


  <<__Override>>
  protected function getPidFilePath(): string {
    return $this->options->tempDir.'/php-fpm.pid';
  }
}
