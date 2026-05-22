<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\datetime\Plugin\views\argument\MonthDate;

/**
 * Overriding the views argument plugin "datetime_month".
 */
class DatetimeMonthDate extends MonthDate {

  use DatetimeDateTrait;

}
