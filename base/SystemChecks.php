<?hh

class SystemChecks {
  public static function CheckAll(): void {
    self::CheckPortAvailability();
    self::CheckCPUFreq();
    self::CheckTCPTimeWaitReuse();
  }

  private static function CheckTCPTimeWaitReuse(): void {
    $f = '/proc/sys/net/ipv4/tcp_tw_reuse';
    if (!file_exists($f)) {
      return;
    }
    $enabled = trim(file_get_contents($f)) === '1';
    invariant(
      $enabled,
      'TCP TIME_WAIT socket re-use must be enabled - see time_wait.md for details',
    );
  }

  private static function CheckCPUFreq(): void {
    $sys_cpu_root = '/sys/devices/system/cpu';
    if (!file_exists($sys_cpu_root)) {
      return;
    }
    foreach (glob($sys_cpu_root.'/*') as $path) {
      if (!preg_match('/cpu[0-9]+/', $path)) {
        continue;
      }
      $gov_file = $path.'/cpufreq/scaling_governor';
      if (!file_exists($gov_file)) {
        continue;
      }
      $gov = trim(file_get_contents($gov_file));
      invariant(
        $gov === 'performance',
        'Unsuitable CPU speed policy - see cpufreq.md'
      );
    }
  }

  private static function CheckPortAvailability(): void {
    $ports = Vector {
      PerfSettings::HttpPort(),
      PerfSettings::HttpAdminPort(),
      PerfSettings::FastCGIPort(),
      PerfSettings::FastCGIAdminPort(),
    };
    $busy_ports = Vector { };
    foreach ($ports as $port) {
      $result = @fsockopen('localhost', $port);
      if ($result !== false) {
        fclose($result);
        $busy_ports[] = $port;
      }
    }
    if ($busy_ports) {
      fprintf(
        STDERR,
        "Ports %s are required, but already in use. You can find out what ".
        "processes are using them with:\n  sudo lsof -P %s\n",
        implode(', ', $busy_ports),
        implode(' ', $busy_ports->map($x ==> '-i :'.$x)),
      );
      exit(1);
    }
  }
}
