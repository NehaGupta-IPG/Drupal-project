<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Custom as CoreCustom;

/**
 * Overriding the views field plugin "custom".
 */
class Custom extends CoreCustom {

  use FieldPluginTrait;

}
