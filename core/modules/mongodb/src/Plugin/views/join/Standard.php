<?php

namespace Drupal\mongodb\Plugin\views\join;

use Drupal\views\Plugin\views\join\Standard as CoreStandard;

/**
 * Overrides the views join plugin "standard".
 */
class Standard extends CoreStandard {

  use JoinPluginTrait;

}
