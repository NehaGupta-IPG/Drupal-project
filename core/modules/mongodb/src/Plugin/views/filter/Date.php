<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\Date as CoreDate;
use MongoDB\BSON\UTCDateTime;

/**
 * Overriding the views filter plugin "date".
 */
class Date extends CoreDate {

  use DateTrait;

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    $a = intval(strtotime($this->value['min'], 0));
    $b = intval(strtotime($this->value['max'], 0));

    if (!empty($this->value['type']) && $this->value['type'] == 'offset') {
      $time = \Drupal::time()->getRequestMicroTime();
      $a = new UTCDateTime(intval(($time - $a) * 1000));
      $b = new UTCDateTime(intval(($time + $b) * 1000));
    }
    else {
      $a = new UTCDateTime($a * 1000);
      $b = new UTCDateTime($b * 1000);
    }
    if (strtoupper($this->operator) == 'BETWEEN') {
      $this->query->addCondition($this->options['group'], $field, $a, '>=');
      $this->query->addCondition($this->options['group'], $field, $b, '<=');
    }
    else {
      $condition = $this->query->getConnection()->condition('OR');
      $condition->condition($field, $a, '<');
      $condition->condition($field, $b, '>');
      $this->query->addCondition($this->options['group'], $condition);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    $value = intval(strtotime($this->value['value'], 0));

    if (!empty($this->value['type']) && $this->value['type'] == 'offset') {
      $time = \Drupal::time()->getRequestMicroTime();
      $value = new UTCDateTime(intval(($time + $value) * 1000));
    }
    else {
      $value = new UTCDateTime($value * 1000);
    }
    $this->query->addCondition($this->options['group'], $field, $value, strtoupper($this->operator));
  }

}
