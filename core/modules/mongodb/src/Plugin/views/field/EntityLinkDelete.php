<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityLinkDelete as CoreEntityLinkDelete;

/**
 * Overriding the views field plugin "entity_link_delete".
 */
class EntityLinkDelete extends CoreEntityLinkDelete {

  use FieldPluginTrait;

}
