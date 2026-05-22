<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\node\Plugin\views\field\RevisionLink as CoreRevisionLink;

/**
 * Overriding the views field plugin "node_revision_link".
 */
class RevisionLink extends CoreRevisionLink {

  use FieldPluginTrait;

}
