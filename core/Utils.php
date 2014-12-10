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

final class Utils {
  public static function ExtractTar(
    string $tar_file,
    string $extract_to,
  ) {
    // Wrong argument order?
    invariant(is_dir($extract_to), '%s is not a directory', $extract_to);
    $flags = null;
    if (substr($tar_file, -3) === '.gz') {
      $flags = '-zxf';
    } else if (substr($tar_file, -4) === '.bz2') {
      $flags = '-jxf';
    }
    invariant($flags !== null, "Couldn't guess compression for %s", $tar_file);

    shell_exec(self::EscapeCommand(Vector {
      'tar',
      '-C', $extract_to,
      $flags,
      $tar_file,
    }));
  }
  public static function EscapeCommand(Vector<string> $command): string {
    return implode(' ', $command->map($x ==> escapeshellarg($x)));
  }
}
