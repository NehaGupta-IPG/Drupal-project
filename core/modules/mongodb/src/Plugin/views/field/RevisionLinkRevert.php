<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\node\Plugin\views\field\RevisionLinkRevert as CoreRevisionLinkRevert;

/**
 * Overriding the views field plugin "node_revision_link_revert".
 */
class RevisionLinkRevert extends CoreRevisionLinkRevert {

  use FieldPluginTrait;

}
