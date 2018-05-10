<?php
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

require_once('config_check_base.php');

print json_encode(
  [
    'HHVM_VERSION' => HHVM_VERSION,
    'hhvm.jit' =>
      feature((bool) ini_get('hhvm.jit'), true),
    'hhvm.jit_pseudomain' =>
      feature((bool) ini_get('hhvm.jit_pseudomain'), true),
    'libpcre has JIT' =>
      feature((bool) ini_get('hhvm.pcre.jit'), true),
    'HHVM build type' =>
      feature(ini_get('hhvm.build_type'), 'Release')
  ],
  JSON_PRETTY_PRINT
)."\n";
