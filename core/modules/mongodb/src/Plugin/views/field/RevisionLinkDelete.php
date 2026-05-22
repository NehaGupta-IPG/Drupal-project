<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\node\Plugin\views\field\RevisionLinkDelete as CoreRevisionLinkDelete;

/**
 * Overriding the views field plugin "node_revision_link_delete".
 */
class RevisionLinkDelete extends CoreRevisionLinkDelete {

  use FieldPluginTrait;

}
