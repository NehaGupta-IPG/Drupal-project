<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\datetime\Plugin\views\argument\YearMonthDate;

/**
 * Overriding the views argument plugin "datetime_year_month".
 */
class DatetimeYearMonthDate extends YearMonthDate {

  use DatetimeDateTrait;

}
