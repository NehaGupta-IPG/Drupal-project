<?php

namespace Drupal\mongodb\Plugin\views\join;

use Drupal\views\Plugin\views\join\Subquery as CoreSubquery;

/**
 * Overrides the views join plugin "subquery".
 */
class Subquery extends CoreSubquery {

  use JoinPluginTrait;

}
