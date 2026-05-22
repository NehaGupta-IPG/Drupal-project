<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\ManyToOne as CoreManyToOne;

/**
 * Overriding the views filter plugin "many_to_one".
 */
class ManyToOne extends CoreManyToOne {

  use InOperatorTrait;
  use ManyToOneTrait;

}
