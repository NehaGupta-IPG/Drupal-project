<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\NumericArgument as CoreNumericArgument;

/**
 * Overriding the views argument plugin "numeric".
 */
class NumericArgument extends CoreNumericArgument {

  use NumericArgumentTrait;

}
