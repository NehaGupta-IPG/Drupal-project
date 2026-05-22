<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityLinkEdit as CoreEntityLinkEdit;

/**
 * Overriding the views field plugin "entity_link_edit".
 */
class EntityLinkEdit extends CoreEntityLinkEdit {

  use FieldPluginTrait;

}
