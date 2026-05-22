<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Boolean as CoreBoolean;

/**
 * Overriding the views field plugin "boolean".
 */
class Boolean extends CoreBoolean {

  use FieldPluginTrait;

}
