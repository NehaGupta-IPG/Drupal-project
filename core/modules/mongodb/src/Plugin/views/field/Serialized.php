<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Serialized as CoreSerialized;

/**
 * Overriding the views field plugin "serialized".
 */
class Serialized extends CoreSerialized {

  use FieldPluginTrait;

}
