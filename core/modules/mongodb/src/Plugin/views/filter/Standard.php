<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\Standard as CoreStandard;

/**
 * Overriding the views filter plugin "standard".
 */
class Standard extends CoreStandard {

  use FilterPluginTrait;

}
