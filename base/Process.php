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

abstract class Process {
  protected ?resource $process;
  protected ?resource $stdin;
  protected ?resource $stdout;
  protected ?string $command;
  protected bool $suppress_stdout = false;

  private static Vector<Process> $processes = Vector {};

  public function __construct(private string $executablePath) {
    self::$processes[] = $this;
  }

  final public static function cleanupAll() {
    foreach (self::$processes as $process) {
      $process->__destruct();
    }
    self::$processes = Vector { };
  }

  abstract protected function getArguments(): Vector<string>;
  protected function getEnvironmentVariables(): Map<string, string> {
    return Map { };
  }

  public function getExecutablePath(): string {
    return $this->executablePath;
  }

  abstract public function start(): void;

  public function startWorker(
    ?string $outputFileName = null,
    float $delayProcessLaunch = 0.5,
    bool $trace = false,
  ): void {
    $executable = $this->getExecutablePath();

    $this->command = $executable.' '.implode(
      ' ',
      $this->getArguments()->map($x ==> escapeshellarg($x)),
    );
    if ($this->suppress_stdout) {
      $this->command .= ' >/dev/null';
    }
    $use_pipe = ($outputFileName === null);
    $spec = [
      0 => ['pipe', 'r'], // stdin
      1 => $use_pipe ? ['pipe', 'w'] : ['file', $outputFileName, 'a'], // stdout
      // not currently using file descriptor 2 (stderr)
    ];
    $pipes = [];
    $env = new Map($_ENV);
    $env->setAll($this->getEnvironmentVariables());

    if ($trace) {
      if ($use_pipe) {
        fprintf(STDERR, "%s\n", $this->command);
      } else {
        fprintf(STDERR, "%s >> %s\n", $this->command, $outputFileName);
      }
    }

    $proc = proc_open($this->command, $spec, $pipes, null, $env);

    // Give the shell some time to figure out if it could actually launch the
    // process
    Process::sleepSeconds($delayProcessLaunch);
    invariant(
      $proc && proc_get_status($proc)['running'] === true,
      'failed to start process: %s',
      $this->command
    );

    $this->process = $proc;
    $this->stdin = $pipes[0];
    if ($use_pipe) {
      $this->stdout = $pipes[1];
    }
  }

  public function isRunning(): bool {
    $pid = $this->getPid();
    if ($pid !== null) {
      return (bool) posix_getpgid($this->getPid());
    }
    return false;
  }

  public function stop(): void {
    if (!$this->isRunning()) {
      return;
    }

    if (is_resource($this->stdin)) {
      pclose($this->stdin);
    }
    if (is_resource($this->stdout)) {
      pclose($this->stdout);
    }
    $pid = $this->getPid();
    if ($pid !== null) {
      posix_kill($pid, SIGTERM);
    }
  }

  protected function getPidFilePath(): ?string {
    return null;
  }

  public function getPid(): ?int {
    $pid_file = $this->getPidFilePath();
    if ($pid_file && file_exists($pid_file)) {
      return (int) trim(file_get_contents($pid_file));
    }

    $proc = $this->process;
    if ($proc === null) {
      return null;
    }
    $state = proc_get_status($proc);
    if (!$state['running']) {
      return null;
    }
    return $state['pid'];
  }

  public function wait(): void {
    $pid = $this->getPid();
    if ($pid === null) {
      return;
    }
    $status = null;
    pcntl_waitpid($pid, $status);
  }

  public function __destruct() {
    if ($this->isRunning()) {
      $this->stop();
    }
  }

  public static function sleepSeconds(float $secs): void {
    usleep($secs * 1e06);
  }
}
