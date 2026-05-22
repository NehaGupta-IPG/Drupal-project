<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\FileSize as CoreFileSize;

/**
 * Overriding the views field plugin "file_size".
 */
class FileSize extends CoreFileSize {

  use FieldPluginTrait;

}
