<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a database index.
 */
final class Index extends KeyBase {

  /**
   * Constructor.
   *
   * @param string $name
   *   The index name.
   * @param list<KeyColumn|string|array{0:string, 1:int}> $columns
   *   A mix of key column specifiers, being KeyColumn objects, strings naming
   *   columns, or arrays of two elements, column name and length, specifying a
   *   prefix of the named column.
   * @param Property|array<string,array<string,mixed>> $dbSpecificExtra
   *   (Optional) If you need to define an index requiring information not
   *   included elsewhere, you can specify it for each database backend.
   *   Specify this as an associative array having the database type ('mysql',
   *   'sqlite', 'pgsql', 'oracle', etc.) as the key, and an array of extra
   *   information <string,mixed> as the value.
   */
  public function __construct(
    public readonly string $name,
    array $columns,
    public readonly Property|array $dbSpecificExtra = Property::Undefined,
  ) {
    parent::__construct($columns);
  }

}
