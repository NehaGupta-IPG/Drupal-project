<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\Equality as CoreEquality;

/**
 * Overriding the views filter plugin "equality".
 */
class Equality extends CoreEquality {

  use FilterPluginTrait;

}
