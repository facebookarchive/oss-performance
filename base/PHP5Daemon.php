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

final class PHP5Daemon extends PHPEngine {
  private PerfTarget $target;

  public function __construct(
    private PerfOptions $options,
  ) {
    $this->target = $options->getTarget();
    parent::__construct((string) $options->php5);
  }

  public function start(): void {
    parent::startWorker(
      $this->options->daemonOutputFileName('php5'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
  }

  protected function getArguments(): Vector<string> {
    return Vector {
      '-b', '127.0.0.1:'.PerfSettings::FastCGIPort(),
      '-c', OSS_PERFORMANCE_ROOT.'/conf/',
    };
  }

  protected function getEnvironmentVariables(): Map<string, string> {
    return Map {
      'PHP_FCGI_CHILDREN' => '60',
      'PHP_FCGI_MAX_REQUESTS' => '0',
    };
  }
}
