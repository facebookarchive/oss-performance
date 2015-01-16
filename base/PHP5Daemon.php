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

  public function getPid(): ?int {
    $pid = parent::getPid();
    if ($pid === null) {
      return null;
    }

    // proc_open() uses the shell to spawn PHP.
    // Bash just does an exec() in simple cases, so we already have the right
    // PID.
    if ($this->isPHPCGIProcess($pid)) {
      return $pid;
    }

    // Dash always does a fork() + exec(), so we actually want the PID of one
    // of the children.
    foreach (glob('/proc/*') as $candidate) {
      if (!preg_match(',^/proc/[0-9]+(/|$),', $candidate)) {
        continue;
      }
      if (!file_exists($candidate.'/stat')) {
        continue;
      }
      $stat = explode(' ', file_get_contents($candidate.'/stat'));
      $cpid = (int) $stat[0];
      $ppid = (int) $stat[3];
      if ($ppid === $pid && $this->isPHPCGIProcess($cpid)) {
        return $cpid;
      }
    }
    return null;
  }

  private function isPHPCGIProcess(int $pid): bool {
    // Allow 'php-cgi', 'php5-cgi', 'php7-cgi-20141209', etc
    $exe = '/proc/'.$pid.'/exe';
    if (!file_exists($exe)) {
      return false;
    }
    return (bool) preg_match('/php.+-cgi/', readlink($exe));
  }

  protected function getEnvironmentVariables(): Map<string, string> {
    return Map {
      'PHP_FCGI_CHILDREN' => (string) $this->options->phpFCGIChildren,
      'PHP_FCGI_MAX_REQUESTS' => '0',
    };
  }

  public function __toString(): string {
    return (string) $this->options->php5;
  }
}