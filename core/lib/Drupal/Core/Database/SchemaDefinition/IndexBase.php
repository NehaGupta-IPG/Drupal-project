<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Base class for database indexes.
 *
 * Concrete subclasses describe the index technology to be used. Not every
 * database engine supports every index technology; each database driver
 * declares the index definition classes it supports, and skips the ones it
 * does not. Use AlternativeIndexes to specify a fallback index to be created
 * when the preferred index technology is not supported.
 */
abstract class IndexBase extends KeyBase {

  /**
   * Constructor.
   *
   * @param string $name
   *   The index name.
   * @param list<KeyColumn|string|array{0:string, 1:int}> $columns
   *   A mix of key column specifiers, being KeyColumn objects, strings naming
   *   columns, or arrays of two elements, column name and length, specifying a
   *   prefix of the named column.
   * @param bool $limitedLengthAllowed
   *   Defines if limited length columns are allowed.
   */
  public function __construct(
    public readonly string $name,
    array $columns,
    bool $limitedLengthAllowed,
  ) {
    parent::__construct($columns, $limitedLengthAllowed);
  }

}
