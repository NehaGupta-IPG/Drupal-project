<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\options\Plugin\views\argument\NumberListField as CoreNumberListField;

/**
 * Overriding the views argument plugin "number_list_field".
 */
class NumberListField extends CoreNumberListField {

  use NumericArgumentTrait;

}
