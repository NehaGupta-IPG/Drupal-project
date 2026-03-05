<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Provides naming methods for auto-generated JSON hotpath indexes.
 */
trait JsonpathIndexTrait {

  /**
   * Prefix for auto-generated index names.
   */
  protected const AUTO_GENERATED_INDEX_PREFIX = 'auto_gen_';

  /**
   * Get the index name for a JSON hotpath.
   *
   * @param string $field
   *   Source field.
   * @param string $jsonpath
   *   The jsonpath.
   *
   * @return string
   *   The index name.
   */
  public static function getJsonpathIndexName(string $field, string $jsonpath): string {
    return sprintf(
      '%s_%s',
      self::getJsonpathIndexPrefix($field),
      sha1($jsonpath),
    );
  }

  /**
   * Get the prefix for auto-generated indexes for a given source field.
   *
   * @param string $field
   *   Source field.
   *
   * @return string
   *   Prefix for auto-generated indexes.
   */
  public static function getJsonpathIndexPrefix(string $field): string {
    return sprintf('%s%s', self::AUTO_GENERATED_INDEX_PREFIX, $field);
  }

}
