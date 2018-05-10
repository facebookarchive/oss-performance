<?hh
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

final class SugarCRMHomePageTarget extends SugarCRMTarget {

  <<__Override>>
  protected function getSanityCheckString(): string {
    // FIXME: Sanity check does not currently support logging in, so this is
    // bogus
    return 'SugarCRM Inc.';
  }

  <<__Memoize>>
  public function getSiegeRCPath(): ?string {
    $path = tempnam($this->options->tempDir, 'siegerc');
    $query_data = implode(
      '&',
      (Map {
         'module' => 'Users',
         'action' => 'Authenticate',
         'user_name' => 'admin',
         'user_password' => 'admin',
       })->mapWithKey(($k, $v) ==> $k.'='.urlencode($v)),
    );
    $config = sprintf(
      "login-url = http://%s:%d/index.php POST %s\n",
      gethostname(),
      PerfSettings::HttpPort(),
      $query_data,
    );
    file_put_contents($path, $config);
    return $path;
  }
}
