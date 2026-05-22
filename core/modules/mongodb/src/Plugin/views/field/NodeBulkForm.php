<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\node\Plugin\views\field\NodeBulkForm as CoreNodeBulkForm;

/**
 * Overriding the views field plugin "node_bulk_form".
 */
class NodeBulkForm extends CoreNodeBulkForm {

  use FieldPluginTrait;

}
