<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityLabel as CoreEntityLabel;

/**
 * Overriding the views field plugin "entity_label".
 */
class EntityLabel extends CoreEntityLabel {

  use FieldPluginTrait;

}
