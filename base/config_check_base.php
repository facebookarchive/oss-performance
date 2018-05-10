<?php
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
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
