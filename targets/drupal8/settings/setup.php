<?php
/*
 *  Copyright (c) 2014-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

$copy = \Drupal\Core\Site\Settings::getAll();
$copy['php_storage']['twig']['class'] = "Drupal\Component\PhpStorage\FileStorage";
$foo = new \Drupal\Core\Site\Settings($copy);
require_once "core/themes/engines/twig/twig.engine";

while ($f = trim(fgets(STDIN))) {
  if (stripos($f, 'tests') === FALSE) {
    twig_render_template(substr($f,2), []);
  }
}
