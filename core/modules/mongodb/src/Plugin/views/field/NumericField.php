<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\NumericField as CoreNumericField;

/**
 * Overriding the views field plugin "numeric".
 */
class NumericField extends CoreNumericField {

  use FieldPluginTrait;

}
