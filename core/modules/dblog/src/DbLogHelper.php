<?php

declare(strict_types=1);

namespace Drupal\dblog;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains methods to format dblog records for debugging and reporting.
 */
class DbLogHelper {

  /**
   * Formats a dblog record so that it can be exported in Yaml.
   *
   * @param array $record
   *   The dblog record.
   *
   * @return array
   *   The formatted record suitable for yaml.
   */
  public static function formatRecordForYamlReport(array $record): array {
    if (is_string($record['variables'])) {
      $record['variables'] = unserialize($record['variables']);
    }
    // Force a new order of keys, and readable values.
    return [
      'severity' => (string) (RfcLogLevel::getLevels()[$record['severity']] ?? $record['severity']),
      'type' => $record['type'],
      'message' => $record['message'],
      'variables' => static::formatValueForYamlReport($record['variables']),
    ] + $record;
  }

  /**
   * Formats a dblog record as Yaml.
   *
   * @param array $record
   *   The dblog record.
   *
   * @return string
   *   The formatted record as yaml.
   */
  public static function formatRecordAsYaml(array $record): string {
    $formatted = static::formatRecordForYamlReport($record);
    return Yaml::encode($formatted);
  }

  /**
   * Formats a value so that it can be exported in Yaml.
   *
   * @param mixed $value
   *   The original value.
   *
   * @return array|mixed|\Symfony\Component\Yaml\Tag\TaggedValue
   *   A value suitable for Yaml.
   */
  public static function formatValueForYamlReport(mixed $value) {
    if (is_array($value)) {
      return array_map(static::formatValueForYamlReport(...), $value);
    }
    if (is_object($value)) {
      return match (TRUE) {
        $value instanceof TranslatableMarkup => new TaggedValue('t', (string) $value),
        $value instanceof MarkupInterface => new TaggedValue('markup', (string) $value),
        $value instanceof \stdClass => new TaggedValue('stdClass', static::formatValueForYamlReport((array) $value)),
        default => new TaggedValue('object', get_class($value)),
      };
    }
    return $value;
  }

}
