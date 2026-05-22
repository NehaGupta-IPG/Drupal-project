<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\ManyToOne as CoreManyToOne;

/**
 * Overriding the views argument plugin "many_to_one".
 */
class ManyToOne extends CoreManyToOne {

  use ManyToOneTrait;

}
