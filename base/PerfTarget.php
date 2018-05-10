<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

abstract class PerfTarget {
  public function install(): void {}

  abstract protected function getSanityCheckString(): string;
  abstract public function getSourceRoot(): string;

  protected function getSanityCheckPath(): string {
    return '/';
  }

  final public function sanityCheck(): void {
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    $url =
      'http://'.
      gethostname().
      ':'.
      PerfSettings::HttpPort().
      $this->getSanityCheckPath();
    $content = file_get_contents($url, /* include path = */ false, $ctx);
    invariant(
      strstr($content, $this->getSanityCheckString()) !== false,
      'Failed to find string "%s" in %s',
      $this->getSanityCheckString(),
      $url,
    );
  }

  <<__Memoize>>
  final protected function getAssetsDirectory(): string {
    $class = get_class($this);
    $file = (new ReflectionClass($class))->getFileName();
    return dirname($file);
  }

  public function postInstall(): void {}

  final public function applyPatches(): void {
    $dir = $this->getAssetsDirectory().'/patches/';
    if (!file_exists($dir)) {
      return;
    }

    $patches = glob($dir.'/*.patch');
    sort(&$patches);

    $dir = escapeshellarg($this->getSourceRoot());

    foreach ($patches as $patch) {
      $patch = escapeshellarg($patch);
      exec('patch -p1 -d '.$dir.' < '.$patch);
    }
  }

  final public function getURLsFile(): string {
    $class = get_class($this);
    $dir = $this->getAssetsDirectory();
    return $dir.'/'.$class.'.urls';
  }

  /*
   * Blacklist paths from HHVM internal statistics. Some frameworks
   * make a request to themselves (eg wordpress calls /wp-cron.php on every
   * page load). The CPU time/instructions taken by this page don't actually
   * affect RPS, so reporting them isn't useful.
   *
   * The time taken to trigger the request is still included in the wall time
   * for the relevant page (eg index.php).
   *
   * THIS DOES NOT AFFECT THE OVERALL RESULTS FROM SIEGE.
   */
  public function ignorePath(string $path): bool {
    return false;
  }

  /*
   * Given the data in the .sql might be old, there could be some /ridiculously/
   * expensive stuff to do on the first request - for example, wordpress will
   * make a request to rpc.pingomatic.com, and it'll upgrade itself.
   */
  public function needsUnfreeze(): bool {
    return false;
  }

  public function unfreeze(PerfOptions $options): void {
    invariant_violation(
      'If you override needsUnfreeze(), you must override unfreeze() too.',
    );
  }

  public function safeCommand(Vector<string> $command): string {
    // Temporary - too many pull requests in flight, clean up callers introduced
    // by them once they're landed
    return Utils::EscapeCommand($command);
  }

  public function __toString(): string {
    return get_class($this);
  }

  public function getSiegeRCPath(): ?string {
    return null;
  }
}
