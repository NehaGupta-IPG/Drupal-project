<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\BulkForm as CoreBulkForm;

/**
 * Overriding the views field plugin "bulk_form".
 */
class BulkForm extends CoreBulkForm {

  use FieldPluginTrait;

}
