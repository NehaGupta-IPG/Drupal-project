<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityLink as CoreEntityLink;

/**
 * Overriding the views field plugin "entity_link".
 */
class EntityLink extends CoreEntityLink {

  use FieldPluginTrait;

}
