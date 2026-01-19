<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Core\Database\Exception\SchemaDefinitionException;

/**
 * Describes a database table's column.
 */
final class Column implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The column name.
   * @param ?ColumnType $type
   *   (Optional) The column data type, generic. Each database will map this to
   *   its own definition. This argument is mandatory unless $dbSpecificExtra is
   *   specified. See
   *   \Drupal\Core\Database\SchemaDefinition\ColumnType for allowed values.
   * @param ?string $description
   *   (Optional) A string in non-markup plain text describing this field and
   *   its purpose. References to other tables should be enclosed in curly
   *   brackets. For example, the users_data table 'uid' field description
   *   might contain "The {users}.uid this record affects.".
   * @param ?bool $serialize
   *   (Optional) A boolean indicating whether the field will be stored as a
   *   serialized string. If NULL, it is not specified. Defaults to NULL.
   * @param ?ColumnSize $size
   *   (Optional) The column data size. This is a hint about the largest value
   *   the column will store. See
   *   \Drupal\Core\Database\SchemaDefinition\ColumnSize for allowed values.
   * @param ?bool $notNull
   *   (Optional)  If true, no NULL values will be allowed in this database
   *   column. If false, NULL values will be allowed. If NULL, it is not
   *   specified. Defaults to NULL.
   * @param StringValue|IntValue|FloatValue|NullValue|null $default
   *   (Optional) The field's default value.
   * @param ?int $length
   *   (Optional) The maximum length of a type 'char', 'varchar' or 'text'
   *   field. Ignored for other field types.
   * @param ?bool $unsigned
   *   (Optional) A boolean indicating whether a type 'int', 'float' and
   *   'numeric' only is signed or unsigned. If NULL, it is not specified.
   *    Defaults to NULL.
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
   *   already the default behavior. If NULL, it is not specified. Defaults to
   *   NULL.
   * @param array<string,array<string,mixed>>|null $dbSpecificExtra
   *   (Optional) If you need to use a column type not included in the
   *   officially supported list of types above, you can specify a type for
   *   each database backend. Specify this as an associative array having the
   *   database type ('mysql', 'sqlite', 'pgsql', 'oracle', etc.) as the key,
   *   and an array of extra information <string,mixed> as the value. If NULL,
   *   it is not specified. Defaults to NULL.
   *
   * @see \Drupal\Core\Database\SchemaDefinition\ColumnType
   * @see \Drupal\Core\Database\SchemaDefinition\ColumnSize
   */
  public function __construct(
    public readonly string $name,
    public readonly ?ColumnType $type = NULL,
    public readonly ?string $description = NULL,
    public readonly ?bool $serialize = NULL,
    public readonly ?ColumnSize $size = NULL,
    public readonly ?bool $notNull = NULL,
    public readonly StringValue|IntValue|FloatValue|NullValue|null $default = NULL,
    public readonly ?int $length = NULL,
    public readonly ?bool $unsigned = NULL,
    public readonly ?int $precision = NULL,
    public readonly ?int $scale = NULL,
    public readonly ?bool $binary = NULL,
    public readonly ?array $dbSpecificExtra = NULL,
  ) {
    $this->validate();
  }

  /**
   * Validates the properties of the value object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  private function validate(): void {

    // If no column type specified, a db specific one should be set.
    if ($this->type === NULL && $this->dbSpecificExtra === NULL) {
      throw new SchemaDefinitionException("Neither 'type' not 'dbSpecificExtra' for column '{$this->name}'");
    }

    // Cannot set notNull for serial columns.
    if ($this->notNull !== NULL && $this->type === ColumnType::Serial) {
      throw new SchemaDefinitionException("Cannot set 'notNull' for {$this->type->value} column '{$this->name}'");
    }

    // Can only set unsigned for some column types.
    if ($this->unsigned !== NULL && $this->type !== NULL && !in_array($this->type, [
      ColumnType::Int,
      ColumnType::Float,
      ColumnType::Numeric,
    ], TRUE)) {
      throw new SchemaDefinitionException("Cannot set 'unsigned' for {$this->type->value} column '{$this->name}'");
    }

    // Can only set length for some column types.
    if ($this->length !== NULL && $this->type !== NULL && !in_array($this->type, [
      ColumnType::Char,
      ColumnType::Varchar,
      ColumnType::VarcharAscii,
      ColumnType::Text,
    ], TRUE)) {
      throw new SchemaDefinitionException("Cannot set 'length' for {$this->type->value} column '{$this->name}'");
    }

    // Can only set binary for some column types.
    if ($this->binary !== NULL && $this->type !== NULL && !in_array($this->type, [
      ColumnType::Char,
      ColumnType::Varchar,
      ColumnType::VarcharAscii,
      ColumnType::Text,
    ], TRUE)) {
      throw new SchemaDefinitionException("Cannot set 'binary' for {$this->type->value} column '{$this->name}'");
    }

    // Can only set scale of a numeric column.
    if ($this->precision !== NULL && $this->type !== NULL && $this->type !== ColumnType::Numeric) {
      throw new SchemaDefinitionException("Cannot set 'precision' for {$this->type->value} column '{$this->name}'");
    }

    // Can only set precision of a numeric column.
    if ($this->scale !== NULL && $this->type !== NULL && $this->type !== ColumnType::Numeric) {
      throw new SchemaDefinitionException("Cannot set 'scale' for {$this->type->value} column '{$this->name}'");
    }

    // Can only set serialize on a text/blob column.
    if ($this->serialize !== NULL && $this->type !== NULL &&  !in_array($this->type, [
      ColumnType::Text,
      ColumnType::Blob,
    ], TRUE)) {
      throw new SchemaDefinitionException("Cannot set 'serialize' for {$this->type->value} column '{$this->name}'");
    }

    // Check validity of default type.
    if ($this->type !== NULL && $this->default !== NULL) {
      $typeClass = get_class($this->default);
      $allowedTypes = $this->allowedDefaultTypes();
      if (!in_array($typeClass, $allowedTypes)) {
        throw new SchemaDefinitionException("Cannot set a $typeClass default for column '{$this->name}'");
      }
    }

    // Cannot set a null value if notNull is true.
    if ($this->notNull && $this->default && get_class($this->default) === NullValue::class) {
      throw new SchemaDefinitionException("Cannot set a null default for column '{$this->name}': notNull is true");
    }

  }

  /**
   * Returns the valid default value types for the column.
   *
   * @return list<class-string>
   *   The valid value classes.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  private function allowedDefaultTypes(): array {
    if ($this->type === NULL) {
      throw new SchemaDefinitionException("Cannot determine allowed default types for column '{$this->name}'");
    }
    return match ($this->type) {
      ColumnType::Char, ColumnType::Varchar, ColumnType::VarcharAscii, ColumnType::Text, ColumnType::Blob => [
        StringValue::class,
        NullValue::class,
      ],
      ColumnType::Int => [
        IntValue::class,
        NullValue::class,
      ],
      ColumnType::Serial => [],
      ColumnType::Float, ColumnType::Numeric => [
        FloatValue::class,
        NullValue::class,
      ],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $spec = [];
    if ($this->type) {
      $spec['type'] = $this->type->value;
      if ($this->type === ColumnType::Serial) {
        $spec['not null'] = TRUE;
        $spec['unsigned'] = TRUE;
      }
    }
    if ($this->description !== NULL) {
      $spec['description'] = $this->description;
    }
    if ($this->serialize !== NULL) {
      $spec['serialize'] = $this->serialize;
    }
    if ($this->size) {
      $spec['size'] = $this->size->value;
    }
    if ($this->notNull !== NULL) {
      $spec['not null'] = $this->notNull;
    }
    if ($this->default) {
      $spec['default'] = $this->default->value;
    }
    if ($this->length !== NULL) {
      $spec['length'] = $this->length;
    }
    if ($this->unsigned !== NULL) {
      $spec['unsigned'] = $this->unsigned;
    }
    if ($this->precision !== NULL) {
      $spec['precision'] = $this->precision;
    }
    if ($this->scale !== NULL) {
      $spec['scale'] = $this->scale;
    }
    if ($this->binary !== NULL) {
      $spec['binary'] = $this->binary;
    }
    if ($this->dbSpecificExtra !== NULL) {
      foreach ($this->dbSpecificExtra as $extra) {
        foreach ($extra as $key => $value) {
          $spec[$key] = $value;
        }
      }
    }
    return $spec;
  }

}
