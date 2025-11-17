<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a database table's column.
 */
final class Column implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The column name.
   * @param ColumnType $type
   *   (Optional) The column data type, generic. Each database will map this to
   *   its own definition. This argument is mandatory unless $dbSpecificExtra is
   *   specified. See
   *   \Drupal\Core\Database\SchemaDefinition\ColumnType for allowed values.
   * @param ?Value $description
   *   (Optional) A string in non-markup plain text describing this field and
   *   its purpose. References to other tables should be enclosed in curly
   *   brackets. For example, the users_data table 'uid' field description
   *   might contain "The {users}.uid this record affects.".
   * @param ?bool $serialize
   *   (Optional) A boolean indicating whether the field will be stored as a
   *   serialized string.
   * @param ColumnSize $size
   *   (Optional) The column data size. This is a hint about the largest value
   *   the column will store. See
   *   \Drupal\Core\Database\SchemaDefinition\ColumnSize for allowed values.
   * @param ?bool $notNull
   *   (Optional)  If true, no NULL values will be allowed in this database
   *   column. Defaults to false.
   * @param ?Value $default
   *   (Optional) The field's default value. The PHP type of the value
   *   matters: '', '0', and 0 are all different. If you specify '0' as the
   *   default value for a type 'int' field it will not work because '0' is a
   *   string containing the character "zero", not an integer.
   * @param ?int $length
   *   (Optional) The maximal length of a type 'char', 'varchar' or 'text'
   *   field. Ignored for other field types.
   * @param ?bool $unsigned
   *   (Optional) A boolean indicating whether a type 'int', 'float' and
   *   'numeric' only is signed or unsigned. Defaults to FALSE. Ignored for
   *    other field types.
   * @param ?int $precision
   *   (Optional) Mandatory for type 'numeric' fields, indicates the precision
   *   (total number of significant digits). Ignored for other field types.
   * @param ?int $scale
   *   (Optional) Mandatory for type 'numeric' fields, indicates the scale
   *   (decimal digits right of the decimal point). Ignored for other field
   *   types.
   * @param ?bool $binary
   *   (Optional) A boolean indicating that MySQL should force 'char',
   *   'varchar' or 'text' fields to use case-sensitive binary collation. This
   *   has no effect on other database types for which case sensitivity is
   *   already the default behavior.
   * @param array<string,array<string,mixed>>|null $dbSpecificExtra
   *   (Optional) If you need to use a column type not included in the
   *   officially supported list of types above, you can specify a type for
   *   each database backend. Specify this as an associative array having the
   *   database type ('mysql', 'sqlite', 'pgsql', 'oracle', etc.) as the key,
   *   and an array of extra information <string,mixed> as the value.
   *
   * @see \Drupal\Core\Database\SchemaDefinition\ColumnType
   * @see \Drupal\Core\Database\SchemaDefinition\ColumnSize
   */
  public function __construct(
    public readonly string $name,
    public readonly ColumnType $type = ColumnType::Undefined,
    public readonly ?Value $description = NULL,
    public readonly ?bool $serialize = NULL,
    public readonly ColumnSize $size = ColumnSize::Undefined,
    public readonly ?bool $notNull = NULL,
    public readonly ?Value $default = NULL,
    public readonly ?int $length = NULL,
    public readonly ?bool $unsigned = NULL,
    public readonly ?int $precision = NULL,
    public readonly ?int $scale = NULL,
    public readonly ?bool $binary = NULL,
    public readonly ?array $dbSpecificExtra = NULL,
  ) {
  }

}
