<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

class SystemChecks {
  public static function CheckAll(PerfOptions $options): void {
    self::CheckNotRoot();
    self::CheckPortAvailability();
    self::CheckCPUFreq();
    self::CheckTCPTimeWaitReuse();
    self::CheckForAuditd($options);
  }

  private static function CheckNotRoot(): void {
    invariant(getmyuid() !== 0, 'Run this script as a regular user.');
  }

  private static function CheckForAuditd(PerfOptions $options): void {
    foreach (glob('/proc/*/cmdline') as $cmdline) {
      if (!is_readable($cmdline)) {
        continue;
      }
      if (file_get_contents($cmdline) !== "auditd\0") {
        continue;
      }
      if ($options->notBenchmarking) {
        fprintf(
          STDERR,
          "WARNING: auditd is running, and can significantly skew ".
          "benchmark and profiling results. Please disable it.\n",
        );
        sleep(3);
        return;
      }
      invariant_violation(
        "auditd is running, and can significantly skew benchmark and ".
        "profiling results. Either disable it, or pass ".
        "--i-am-not-benchmarking to continue anyway.",
      );
    }
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
        'Unsuitable CPU speed policy - see cpufreq.md',
      );
    }
  }

  private static function CheckPortAvailability(): void {
    $ports = Vector {
      PerfSettings::HttpPort(),
      PerfSettings::HttpAdminPort(),
      PerfSettings::BackendPort(),
      PerfSettings::BackendAdminPort(),
    };
    $busy_ports = Vector {};
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
