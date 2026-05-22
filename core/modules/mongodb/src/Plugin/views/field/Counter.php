<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Counter as CoreCounter;

/**
 * Overriding the views field plugin "counter".
 */
class Counter extends CoreCounter {

  use FieldPluginTrait;

}
