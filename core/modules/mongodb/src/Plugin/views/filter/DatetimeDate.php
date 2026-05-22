<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\views\filter\Date;

// cspell:ignore datestring

/**
 * Overriding the views filter plugin "datetime".
 */
class DatetimeDate extends Date {

  use DateTrait;

  /**
   * Override parent method, which deals with dates as integers.
   */
  protected function opBetween($field) {
    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['min'], $timezone);

    // Although both 'min' and 'max' values are required, default empty 'min'
    // value as UNIX timestamp 0.
    $min = (!empty($this->value['min'])) ? $this->value['min'] : '@0';

    // Convert to ISO format and format for query. UTC timezone is used since
    // dates are stored in UTC.
    $a = new DateTimePlus($min, new \DateTimeZone($timezone));
    $b = new DateTimePlus($this->value['max'], new \DateTimeZone($timezone));

    $values_a = [
      'format' => $this->query->getDateFormat(NULL, $this->dateFormat),
      'value' => $this->dateFormatter->format($a->getTimestamp() + $origin_offset, 'custom', $this->dateFormat, DateTimeItemInterface::STORAGE_TIMEZONE),
      'operator' => (strtoupper($this->operator) == 'NOT BETWEEN' ? '<' : '>='),
    ];

    $values_b = [
      'format' => $this->query->getDateFormat(NULL, $this->dateFormat),
      'value' => $this->dateFormatter->format($b->getTimestamp() + $origin_offset, 'custom', $this->dateFormat, DateTimeItemInterface::STORAGE_TIMEZONE),
      'operator' => (strtoupper($this->operator) == 'NOT BETWEEN' ? '>' : '<='),
    ];

    if (strtoupper($this->operator) == 'NOT BETWEEN') {
      $or_condition = $this->query->getConnection()->condition('OR');
      $or_condition->condition($field, $values_a, 'DATESTRING');
      $or_condition->condition($field, $values_b, 'DATESTRING');
      $this->query->addCondition($this->options['group'], $or_condition);
    }
    else {
      $this->query->addCondition($this->options['group'], $field, $values_a, 'DATESTRING');
      $this->query->addCondition($this->options['group'], $field, $values_b, 'DATESTRING');
    }
    $last_dot_position = strrpos($field, '.');
    if ($last_dot_position !== FALSE) {
      $this->query->addFilterUnwindPath(substr($field, 0, $last_dot_position));
    }
  }

  /**
   * Override parent method, which deals with dates as integers.
   */
  protected function opSimple($field) {
    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['value'], $timezone);

    // Convert to ISO. UTC timezone is used since dates are stored in UTC.
    $value = new DateTimePlus($this->value['value'], new \DateTimeZone($timezone));
    $values = [
      'format' => $this->query->getDateFormat(NULL, $this->dateFormat),
      'value' => $this->dateFormatter->format($value->getTimestamp() + $origin_offset, 'custom', $this->dateFormat, DateTimeItemInterface::STORAGE_TIMEZONE),
      'operator' => $this->operator,
    ];
    $this->query->addCondition($this->options['group'], $field, $values, 'DATESTRING');
  }

}
