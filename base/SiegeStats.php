<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

trait SiegeStats {
  abstract protected function getLogFilePath(): string;

  public function collectStats(): Map<string, Map<string, num>> {
    $log_lines =
      explode("\n", trim(file_get_contents($this->getLogFilePath())));
    if (count($log_lines) > 1) {
      // Remove possible header line
      array_splice(&$log_lines, 0, 1);
    }
    invariant(
      count($log_lines) === 1,
      'Expected 1 line in siege log file, got %d',
      count($log_lines),
    );
    $log_line = array_pop(&$log_lines);
    $data = (new Vector(explode(',', $log_line)))->map($x ==> trim($x));
    return Map {
      'Combined' => Map {
        'Siege requests' => (int) $data[SiegeFields::TRANSACTIONS],
        'Siege wall sec' => (float) $data[SiegeFields::RESPONSE_TIME],
        'Siege RPS' => (float) $data[SiegeFields::TRANSACTION_RATE],
        'Siege successful requests' => (int) $data[SiegeFields::OKAY],
        'Siege failed requests' => (int) $data[SiegeFields::FAILED],
      },
    };
  }
}
