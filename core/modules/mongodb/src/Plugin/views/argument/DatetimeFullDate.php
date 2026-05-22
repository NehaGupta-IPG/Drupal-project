<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\datetime\Plugin\views\argument\FullDate;

/**
 * Overriding the views argument plugin "datetime_full_date".
 */
class DatetimeFullDate extends FullDate {

  use DatetimeDateTrait;

}
