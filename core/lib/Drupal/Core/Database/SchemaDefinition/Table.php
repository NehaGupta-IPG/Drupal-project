<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a database table.
 */
final class Table implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The table name.
   * @param Property|Column[] $columns
   *   (Optional) An array that describes the table's database columns. This is
   *   normally mandatory. Some methods in the Schema API - for example
   *   Schema::addField() - require partial table definition, so this is
   *   optional to support it.
   * @param Property|string $description
   *   (Optional) A string in non-markup plain text describing this table and
   *   its purpose. References to other tables should be enclosed in curly
   *   brackets.
   * @param Property|PrimaryKey $primaryKey
   *   (Optional) The primary key of the table.
   * @param Property|UniqueKey[] $uniqueKeys
   *   (Optional) An array of unique keys for the table.
   * @param Property|Index[] $indexes
   *   (Optional) An array of indexes for the table.
   * @param Property|ForeignKey[] $foreignKeys
   *   (Optional) An array of foreign keys for the table. This argument is for
   *   documentation purposes only; foreign keys are not created in the
   *   database, nor are they enforced by Drupal.
   * @param Property|array<string,array<string,mixed>> $dbSpecificExtra
   *   (Optional) If you need to define a table requiring information not
   *   included elsewhere, you can specify it for each database backend.
   *   Specify this as an associative array having the database type ('mysql',
   *   'sqlite', 'pgsql', 'oracle', etc.) as the key, and an array of extra
   *   information <string,mixed> as the value.
   */
  public function __construct(
    public readonly string $name,
    public readonly Property|array $columns = Property::Undefined,
    public readonly Property|string $description = Property::Undefined,
    public readonly Property|PrimaryKey $primaryKey = Property::Undefined,
    public readonly Property|array $uniqueKeys = Property::Undefined,
    public readonly Property|array $indexes = Property::Undefined,
    public readonly Property|array $foreignKeys = Property::Undefined,
    public readonly Property|array $dbSpecificExtra = Property::Undefined,
  ) {
  }

}
