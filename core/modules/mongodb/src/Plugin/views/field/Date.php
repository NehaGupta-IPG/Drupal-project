<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Date as CoreDate;
use Drupal\views\ResultRow;
use MongoDB\BSON\UTCDateTime;

/**
 * Overriding the views field plugin "date".
 */
class Date extends CoreDate {

  use FieldPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    // MongoDB stores date values as MongoDB\BSON\UTCDateTime objects.
    if ($value instanceof UTCDateTime) {
      $value = (int) $value->__toString();
      $value = $value / 1000;
      $value = (string) $value;
    }

    $format = $this->options['date_format'];
    if (in_array($format, ['custom', 'raw time ago', 'time ago', 'raw time hence', 'time hence', 'raw time span', 'time span', 'raw time span', 'inverse time span', 'time span'])) {
      $custom_format = $this->options['custom_date_format'];
    }

    if ($value) {
      $timezone = !empty($this->options['timezone']) ? $this->options['timezone'] : NULL;
      // Will be positive for a datetime in the past (ago), and negative for a
      // datetime in the future (hence).
      $time_diff = intval(\Drupal::time()->getRequestMicroTime() - $value);
      switch ($format) {
        case 'raw time ago':
          return $this->dateFormatter->formatTimeDiffSince($value, ['granularity' => is_numeric($custom_format) ? $custom_format : 2]);

        case 'time ago':
          return $this->t('%time ago', ['%time' => $this->dateFormatter->formatTimeDiffSince($value, ['granularity' => is_numeric($custom_format) ? $custom_format : 2])]);

        case 'raw time hence':
          return $this->dateFormatter->formatTimeDiffUntil($value, ['granularity' => is_numeric($custom_format) ? $custom_format : 2]);

        case 'time hence':
          return $this->t('%time hence', ['%time' => $this->dateFormatter->formatTimeDiffUntil($value, ['granularity' => is_numeric($custom_format) ? $custom_format : 2])]);

        case 'raw time span':
          return ($time_diff < 0 ? '-' : '') . $this->dateFormatter->formatTimeDiffSince($value, ['strict' => FALSE, 'granularity' => is_numeric($custom_format) ? $custom_format : 2]);

        case 'inverse time span':
          return ($time_diff > 0 ? '-' : '') . $this->dateFormatter->formatTimeDiffSince($value, ['strict' => FALSE, 'granularity' => is_numeric($custom_format) ? $custom_format : 2]);

        case 'time span':
          $time = $this->dateFormatter->formatTimeDiffSince($value, ['strict' => FALSE, 'granularity' => is_numeric($custom_format) ? $custom_format : 2]);
          return ($time_diff < 0) ? $this->t('%time hence', ['%time' => $time]) : $this->t('%time ago', ['%time' => $time]);

        case 'custom':
          if ($custom_format == 'r') {
            return $this->dateFormatter->format($value, $format, $custom_format, $timezone, 'en');
          }
          return $this->dateFormatter->format($value, $format, $custom_format, $timezone);

        default:
          return $this->dateFormatter->format($value, $format, '', $timezone);
      }
    }
  }

}
