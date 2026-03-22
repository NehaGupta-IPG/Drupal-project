<?php

declare(strict_types=1);

namespace Drupal\Component\Reporting;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Provides methods to generate readable yaml reports.
 *
 * These can be used in test assertion output, in log messages, etc.
 *
 * @todo Maybe these should be object methods, not static.
 */
class YamlReport {

  /**
   * Formats a value as Yaml for reporting.
   *
   * @param mixed $value
   *   The original value.
   *
   * @return string
   *   The formatted record as yaml.
   */
  public static function format(array $value): string {
    $processed = static::process($value);
    return Yaml::encode($processed);
  }

  /**
   * Formats a value so that it can be exported in Yaml.
   *
   * @param mixed $value
   *   The original value.
   *
   * @return array|scalar|\Symfony\Component\Yaml\Tag\TaggedValue
   *   A value suitable for Yaml.
   */
  public static function process(mixed $value) {
    if (is_array($value)) {
      return array_map(static::process(...), $value);
    }
    if (is_object($value)) {
      if ($value instanceof TaggedValue) {
        return $value;
      }
      return match (TRUE) {
        $value instanceof TranslatableMarkup => new TaggedValue('t', (string) $value),
        $value instanceof MarkupInterface => new TaggedValue('markup', (string) $value),
        $value instanceof \stdClass => new TaggedValue('stdClass', static::process((array) $value)),
        default => new TaggedValue('object', get_class($value)),
      };
    }
    if (is_resource($value)) {
      return new TaggedValue('resource', get_resource_type($value) . ':' . get_resource_id($value));
    }
    return $value;
  }

}
