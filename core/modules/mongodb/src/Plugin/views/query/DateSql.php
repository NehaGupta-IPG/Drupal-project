<?php

namespace Drupal\mongodb\Plugin\views\query;

use Drupal\Core\Database\Connection;
use Drupal\views\Plugin\views\query\DateSqlInterface;

/**
 * The MongoDB implementation of \Drupal\views\Plugin\views\query\DateSql.
 */
class DateSql implements DateSqlInterface {

  /**
   * {@inheritdoc}
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected static $replace = [
    // A full numeric representation of a year, 4 digits: 1999 or 2003.
    'Y' => '%Y',
    // A two digit representation of a year. Examples: 99 or 03.
    // 'y' => '%y',
    // A short textual representation of a month, three letters: Jan through Dec.
    // 'M' => '%b',
    // Numeric representation of a month, with leading zeros: 01 through 12.
    'm' => '%m',
    // Numeric representation of a month, without leading zeros: 1 through 12.
    // 'n' => '%c',
    // A full textual representation of a month, such as January or March:
    // January through December.
    // 'F' => '%M',
    // A textual representation of a day, three letters: Mon through Sun.
    // 'D' => '%a',
    // Day of the month, 2 digits with leading zeros: 01 to 31.
    'd' => '%d',
    // A full textual representation of the day of the week: Sunday through
    // Saturday.
    // 'l' => '%W',
    // Day of the month without leading zeros: 1 to 31.
    // 'j' => '%e',
    // ISO-8601 week number of year, weeks starting on Monday: 42 (the 42nd week
    // in the year).
    'W' => '%V',
    // 24-hour format of an hour with leading zeros: 00 through 23.
    'H' => '%H',
    // 12-hour format of an hour with leading zeros: 01 through 12.
    // 'h' => '%h',
    // Minutes with leading zeros: 00 to 59.
    'i' => '%M',
    // Seconds, with leading zeros: 00 through 59.
    's' => '%S',
    // Uppercase Ante meridiem and Post meridiem: AM or PM.
    // 'A' => '%p',
    '\T' => 'T',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateField($field, $string_date) {
    if ($string_date) {
      return $field;
    }

    return "DATE_ADD('19700101', INTERVAL $field SECOND)";
  }

  /**
   * {@inheritdoc}
   */
  public function getDateFormat($field, $format) {
    return strtr($format, static::$replace);
  }

  /**
   * {@inheritdoc}
   */
  public function setTimezoneOffset($offset) {
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldTimezoneOffset(&$field, $offset) {
  }

}
