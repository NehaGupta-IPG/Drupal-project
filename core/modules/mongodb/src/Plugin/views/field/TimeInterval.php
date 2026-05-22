<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\TimeInterval as CoreTimeInterval;

/**
 * Overriding the views field plugin "time_interval".
 */
class TimeInterval extends CoreTimeInterval {

  use FieldPluginTrait;

}
