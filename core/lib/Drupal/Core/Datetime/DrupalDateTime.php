<?php

namespace Drupal\Core\Datetime;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Extends DateTimePlus().
 *
 * This class extends the basic component and adds in Drupal-specific
 * handling, like translation of the format() method.
 *
 * Static methods in base class can also be used to create DrupalDateTime
 * objects. For example:
 *
 * @code
 * DrupalDateTime::createFromArray(['year' => 2010, 'month' => 9, 'day' => 28])
 * @endcode
 *
 * @see \Drupal\Component\Datetime\DateTimePlus
 */
class DrupalDateTime extends DateTimePlus {

  use StringTranslationTrait;
  use DependencySerializationTrait {
    __sleep as defaultSleep;
  }

  /**
   * Formatted strings translation cache.
   *
   * @var array
   * Translation cache represents an instance storage for formatted date
   * strings. It contains a multidimensional array where:
   * - first level keys - are drupal language codes;
   * - second level keys - are each symbols of given format string (like 'F');
   * - third level keys - are original matched strings related to the symbol;
   * - values - are translated or not-translated original strings (depends on
   *   if a particular symbol represents translatable value according to PHP's
   *   date() format character).
   *
   * For example:
   * @code
   *   [
   *     'en' => [
   *       'F' => [
   *         'November' => t('November'),
   *         'December' => t('December'),
   *       ],
   *       'd' => [
   *         '10' => '10',
   *         '31' => '31',
   *       ],
   *     ],
   *   ]
   * @endcode
   */
  protected $formatTranslationCache = [];

  /**
   * Overrides prepareTimezone().
   *
   * Override basic component timezone handling to use Drupal's
   * knowledge of the preferred user timezone.
   */
  protected function prepareTimezone($timezone) {
    if (empty($timezone)) {
      // Fallback to user or system default timezone.
      $timezone = date_default_timezone_get();
    }
    return parent::prepareTimezone($timezone);
  }

  /**
   * Overrides format().
   *
   * @param string $format
   *   A format string using either PHP's date().
   * @param array $settings
   *   - timezone: (optional) String timezone name. Defaults to the timezone
   *     of the date object.
   *   - langcode: (optional) String two letter language code used to control
   *     the result of the format() method. Defaults to NULL.
   *
   * @return string
   *   The formatted value of the date. Since the format may contain user input,
   *   this value should be escaped when output.
   */
  public function format($format, $settings = []) {
    $langcode = !empty($settings['langcode']) ? $settings['langcode'] : $this->langcode;
    $value = '';
    // Format the date and catch errors.
    try {
      // Encode markers that should be translated. 'A' becomes
      // '\xEF\AA\xFF'. xEF and xFF are invalid UTF-8 sequences,
      // and we assume they are not in the input string.
      // Paired backslashes are isolated to prevent errors in
      // read-ahead evaluation. The read-ahead expression ensures that
      // A matches, but not \A.
      $format = preg_replace(['/\\\\\\\\/', '/(?<!\\\\)([SAaeDlMTF])/'], ["\xEF\\\\\\\\\xFF", "\xEF\\\\\$1\$1\xFF"], $format);

      // Call date_format().
      $format = parent::format($format, $settings);

      // $format will be NULL if there are any errors.
      if ($format !== NULL) {
        // Translates a formatted date string.
        $translation_callback = function ($matches) use ($langcode) {
          $code = $matches[1];
          $string = $matches[2];
          if (!isset($this->formatTranslationCache[$langcode][$code][$string])) {
            $options = ['langcode' => $langcode];
            if ($code == 'F') {
              $options['context'] = 'Long month name';
            }
            if ($code == 'M') {
              $options['context'] = 'Abbreviated month name';
            }
            if ($code == 'S') {
              $options['context'] = 'Day ordinal suffix';
            }

            if ($code == '') {
              $this->formatTranslationCache[$langcode][$code][$string] = $string;
            }
            else {
              // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
              $this->formatTranslationCache[$langcode][$code][$string] = $this->t($string, [], $options);
            }
          }
          return $this->formatTranslationCache[$langcode][$code][$string];
        };

        // Translate the marked sequences.
        $value = preg_replace_callback('/\xEF([SAaeDlMTF]?)(.*?)\xFF/', $translation_callback, $format);
      }
    }
    catch (\Exception $e) {
      $this->errors[] = $e->getMessage();
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    return array_diff($this->defaultSleep(), ['formatTranslationCache']);
  }

}
