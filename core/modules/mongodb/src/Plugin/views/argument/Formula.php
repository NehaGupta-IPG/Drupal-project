<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\Formula as CoreFormula;

/**
 * Overriding the views argument plugin "formula".
 */
class Formula extends CoreFormula {

  use FormulaTrait;

}
