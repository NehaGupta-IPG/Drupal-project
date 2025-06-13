<?php

namespace Drupal\Core\Datetime;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;

/**
 * Preprocess for common/core theme templates.
 *
 * @internal
 */
class DatePreprocess {

  use StringTranslationTrait;

  public function __construct(
    protected DateFormatterInterface $dateFormatter,
  ) {
  }

  /**
   * Prepares variables for time templates.
   *
   * Default template: time.html.twig.
   *
   * @param array $variables
   *   An associative array possibly containing:
   *   - "attributes['timestamp']:".
   *   - "timestamp:".
   *   - "text:".
   */
  public function preprocessTime(array &$variables): void {
    // Format the 'datetime' attribute based on the timestamp.
    // @see https://www.w3.org/TR/html5-author/the-time-element.html#attr-time-datetime
    if (!isset($variables['attributes']['datetime']) && isset($variables['timestamp'])) {
      $variables['attributes']['datetime'] = $this->dateFormatter->format($variables['timestamp'], 'html_datetime', '', 'UTC');
    }

    // If no text was provided, try to auto-generate it.
    if (!isset($variables['text'])) {
      // Format and use a human-readable version of the timestamp, if any.
      if (isset($variables['timestamp'])) {
        $variables['text'] = $this->dateFormatter->format($variables['timestamp']);
      }
      // Otherwise, use the literal datetime attribute.
      elseif (isset($variables['attributes']['datetime'])) {
        $variables['text'] = $variables['attributes']['datetime'];
      }
    }
  }

  /**
   * Prepares variables for datetime form element templates.
   *
   * The datetime form element serves as a wrapper around the date element type,
   * which creates a date and a time component for a date.
   *
   * Default template: datetime-form.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the properties of the element.
   *     Properties used: #title, #value, #options, #description, #required,
   *     #attributes.
   *
   * @see form_process_datetime()
   */
  public function preprocessDatetimeForm(array &$variables): void {
    $element = $variables['element'];

    $variables['attributes'] = [];

    if (isset($element['#id'])) {
      $variables['attributes']['id'] = $element['#id'];
    }
    if (!empty($element['#attributes']['class'])) {
      $variables['attributes']['class'] = (array) $element['#attributes']['class'];
    }

    $variables['content'] = $element;
  }

}
