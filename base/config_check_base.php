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
