<?php
/*
 *  Copyright (c) 2014, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

function feature(
  $actual_value,
  $required_value
) {
  return [
    'OK' => $actual_value === $required_value,
    'Value' => $actual_value,
    'Required Value' => $required_value,
  ];
}

print json_encode(
  [
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
