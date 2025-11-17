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
   * @param ?Column[] $columns
   *   (Optional) An array that describes the table's database columns. This is
   *   normally mandatory. Some methods in the Schema API - for example
   *   Schema::addField() - require partial table definition, so this is
   *   optional to support it.
   * @param ?Value $description
   *   (Optional) A string in non-markup plain text describing this table and
   *   its purpose. References to other tables should be enclosed in curly
   *   brackets.
   * @param ?PrimaryKey $primaryKey
   *   (Optional) The primary key of the table.
   * @param ?UniqueKey[] $uniqueKeys
   *   (Optional) An array of unique keys for the table.
   * @param ?Index[] $indexes
   *   (Optional) An array of indexes for the table.
   * @param ?ForeignKey[] $foreignKeys
   *   (Optional) An array of foreign keys for the table. This argument is for
   *   documentation purposes only; foreign keys are not created in the
   *   database, nor are they enforced by Drupal.
   * @param array<string,array<string,mixed>>|null $dbSpecificExtra
   *   (Optional) If you need to define a table requiring information not
   *   included elsewhere, you can specify it for each database backend.
   *   Specify this as an associative array having the database type ('mysql',
   *   'sqlite', 'pgsql', 'oracle', etc.) as the key, and an array of extra
   *   information <string,mixed> as the value.
   */
  public function __construct(
    public readonly string $name,
    public readonly ?array $columns = NULL,
    public readonly ?Value $description = NULL,
    public readonly ?PrimaryKey $primaryKey = NULL,
    public readonly ?array $uniqueKeys = NULL,
    public readonly ?array $indexes = NULL,
    public readonly ?array $foreignKeys = NULL,
    public readonly ?array $dbSpecificExtra = NULL,
  ) {
  }

}
