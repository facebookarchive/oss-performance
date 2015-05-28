<?php

$copy = \Drupal\Core\Site\Settings::getAll();
$copy['php_storage']['twig']['class'] = "Drupal\Component\PhpStorage\FileStorage";
$foo = new \Drupal\Core\Site\Settings($copy);
require_once "core/themes/engines/twig/twig.engine";

while ($f = trim(fgets(STDIN))) {
  if (stripos($f, 'tests') === FALSE) {
    twig_render_template(substr($f,2), []);
  }
}
