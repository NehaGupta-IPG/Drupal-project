<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\Standard as CoreStandard;

/**
 * Overriding the views argument plugin "standard".
 */
class Standard extends CoreStandard {

  use ArgumentPluginTrait;

}
