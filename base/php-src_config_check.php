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

$opcache_loaded = function_exists('opcache_get_status');
$opcache_enabled =
  $opcache_loaded && is_array(opcache_get_status())
  && opcache_get_status()['opcache_enabled'];
print json_encode(
  array(
    'PHP_VERSION' => PHP_VERSION,
    'PHP_VERSION_ID' => PHP_VERSION_ID,
    'opcache loaded' => feature($opcache_loaded, true),
    'opcache enabled' => feature($opcache_enabled, true),
  ),
  JSON_PRETTY_PRINT
);
