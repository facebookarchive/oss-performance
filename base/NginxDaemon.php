<?hh
/*
 *  Copyright (c) 2014, Facebook, Inc.
 *  All rights reserved.
 
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

final class NginxDaemon extends Process {

  public function __construct(
    private PerfOptions $options,
    private PerfTarget $target,
  ) {
    parent::__construct($this->options->nginx);
  }

  public function start(): void {
    parent::startWorker(
      $this->options->daemonOutputFileName('nginx'),
      $this->options->delayProcessLaunch,
      $this->options->traceSubProcess,
    );
  }

  public function clearAccessLog(): void {
    $log = $this->options->tempDir.'/access.log';
    invariant(
      file_exists($log),
      'access log does not exist, but attempted to clear it'
    );
    $pid = $this->getPid();
    if ($pid !== null) {
      unlink($log);
      posix_kill($pid, SIGUSR1);
    }
  }

  public function collectStats(): Map<string, Map<string, num>> {
    $combined_codes = [];
    $combined_hits = 0;
    $combined_time = 0;
    $combined_bytes = 0;

    $page_results = Map { };

    // Custom format: '$status $body_bytes_sent $request_time "$request"'
    $log = file_get_contents($this->options->tempDir.'/access.log');
    $entries = explode("\n", trim($log));
    $entries_by_request = array();
    foreach ($entries as $entry) {
      $request = explode('"', $entry)[1];
      $entries_by_request[$request][] = $entry;
    }

    foreach ($entries_by_request as $request => $entries) {
      $request_hits = count($entries);
      $combined_hits += $request_hits;
      $page_results[$request] = Map {
        'Nginx hits' => $request_hits,
        'Nginx avg bytes' => 0,
        'Nginx avg time' => 0,
      };

      foreach ($entries as $entry) {
        $parts = explode(' ', $entry);
        list($code, $bytes, $time) = [$parts[0], $parts[1], $parts[2]];

        $combined_codes[$code]++;
        $combined_bytes += $bytes;
        $combined_time += $time;

        $page_results[$request]['Nginx avg bytes'] += $bytes;
        $page_results[$request]['Nginx avg time'] += $time;
        $code_key = 'Nginx '.$code;
        if ($page_results[$request]->containsKey($code_key)) {
          $page_results[$request][$code_key]++;
        } else {
          $page_results[$request][$code_key] = 1;
        }
      }
      $page_results[$request]['Nginx avg bytes'] /= (float) $request_hits;
      $page_results[$request]['Nginx avg time'] /= (float) $request_hits;
    }
    $page_results['Combined'] = Map {
      'Nginx hits' => $combined_hits,
      'Nginx avg bytes' => ((float) $combined_bytes) / $combined_hits,
      'Nginx avg time' => ((float) $combined_time) / $combined_hits,
    };
    foreach ($combined_codes as $code => $count) {
      $page_results['Combined']['Nginx '.$code] = $count;
    }
    return $page_results;
  }

  private function getPidFilePath(): string {
    return $this->options->tempDir.'/nginx.pid';
  }

  <<__Override>>
  protected function getArguments(): Vector<string> {
    return Vector {
      '-c', $this->getGeneratedConfigFile(),
      //
      // Watch out!  The -g arguments to nginx do not accumulate.
      // The last one wins, and is the only one evaluated by nginx.
      //
      '-g', 'daemon off; '.'pid '.$this->getPidFilePath().'; ',
    };
  }

  <<__Override>>
  public function getPid(): ?int {
    $file = $this->getPidFilePath();
    if (!file_exists($file)) {
      return null;
     }
     $pid = (int) file_get_contents($file);
     return $pid ?: null;
  }

  protected function getGeneratedConfigFile(): string {
    $path = $this->options->tempDir.'/nginx.conf';
    if (file_exists($path)) {
      return $path;
    }

    $substitutions = Map {
      '__FASTCGI_PORT__' => PerfSettings::FastCGIPort(),
      '__HTTP_PORT__' => PerfSettings::HttpPort(),
      '__FASTCGI_ADMIN_PORT__' => PerfSettings::FastCGIAdminPort(),
      '__HTTP_ADMIN_PORT__' => PerfSettings::HttpAdminPort(),
      '__NGINX_CONFIG_ROOT__' => OSS_PERFORMANCE_ROOT.'/conf/nginx',
      '__NGINX_TEMP_DIR__' => $this->options->tempDir,
      '__NGINX_KEEPALIVE_TIMEOUT__' =>
        (int)$this->options->maxdelayNginxKeepAlive,
      '__NGINX_FASTCGI_READ_TIMEOUT__' =>
        (int)$this->options->maxdelayNginxFastCGI,
      '__FRAMEWORK_ROOT__' => $this->target->getSourceRoot(),
    };

    $config = file_get_contents(
      OSS_PERFORMANCE_ROOT.'/conf/nginx/nginx.conf.in'
    );
    foreach ($substitutions as $find => $replace) {
      $config = str_replace($find, $replace, $config);
    }
    file_put_contents($path, $config);

    return $path;
  }
}
