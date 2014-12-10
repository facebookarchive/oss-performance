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

function fib($n) {
  assert($n >= 0);
  if ($n === 0 || $n === 1) {
    return 1;
  }
  return fib($n - 1) + fib($n - 2);
}

for ($i = 0; $i < 20; ++$i) {
  if (array_key_exists('n', $_GET)) {
    var_dump(fib((int) $_GET['n']));
  } else {
    var_dump(fib(20));
  }
}
