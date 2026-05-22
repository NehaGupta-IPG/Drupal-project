<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\datetime\Plugin\views\argument\WeekDate;

/**
 * Overriding the views argument plugin "datetime_week".
 */
class DatetimeWeekDate extends WeekDate {

  use DatetimeDateTrait;

}
