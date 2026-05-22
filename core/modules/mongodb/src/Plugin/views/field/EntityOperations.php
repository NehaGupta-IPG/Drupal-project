<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityOperations as CoreEntityOperations;

/**
 * Overriding the views field plugin "entity_operations".
 */
class EntityOperations extends CoreEntityOperations {

  use FieldPluginTrait;

}
