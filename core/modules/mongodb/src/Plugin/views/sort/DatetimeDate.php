<?php

namespace Drupal\mongodb\Plugin\views\sort;

use Drupal\datetime\Plugin\views\sort\Date;

/**
 * Overrides the views sort plugin "datetime".
 */
class DatetimeDate extends Date {

  use DateTrait;

}
