<?php

namespace Drupal\Tests\Core\Utility;

use Drupal\Core\Utility\YamlCacheCollector;

class TestYamlCacheCollector extends YamlCacheCollector {}

function file_exists($file) {
  return FALSE;
}
