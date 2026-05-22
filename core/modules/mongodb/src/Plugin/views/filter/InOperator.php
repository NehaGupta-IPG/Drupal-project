<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator as CoreInOperator;

/**
 * Overriding the views filter plugin "in_operator".
 */
class InOperator extends CoreInOperator {

  use InOperatorTrait;

}
