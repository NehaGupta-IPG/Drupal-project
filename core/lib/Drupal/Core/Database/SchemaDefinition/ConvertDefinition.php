<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * An helper class to convert SchemaDefinition objects to legacy array.
 *
 * @internal
 */
abstract class ConvertDefinition {

  /**
   * Converts an array of Table objects.
   *
   * @internal
   */
  final public static function schemaToArray(array $schema): array {
    $spec = [];
    foreach ($schema as $table) {
      $spec[$table->name] = self::tableToArray($table);
    }
    return $spec;
  }

  /**
   * Converts a Table object.
   *
   * @internal
   */
  final public static function tableToArray(Table $table): array {
    $spec = [];
    if ($table->description) {
      $spec['description'] = $table->description->value;
    }
    if ($table->columns !== NULL) {
      $spec['fields'] = self::columnsToArray($table->columns);
    }
    if ($table->primaryKey !== NULL) {
      $spec['primary key'] = self::primaryKeyToArray($table->primaryKey);
    }
    if ($table->uniqueKeys !== NULL) {
      $spec['unique keys'] = self::uniqueKeysToArray($table->uniqueKeys);
    }
    if ($table->indexes !== NULL) {
      $spec['indexes'] = self::indexesToArray($table->indexes);
    }
    if ($table->foreignKeys !== NULL) {
      $spec['foreign keys'] = self::foreignKeysToArray($table->foreignKeys);
    }
    if ($table->dbSpecificExtra !== NULL) {
      foreach ($table->dbSpecificExtra as $extra) {
        foreach ($extra as $key => $value) {
          $spec[$key] = $value;
        }
      }
    }
    return $spec;
  }

  /**
   * Converts an array of Column objects.
   *
   * @internal
   */
  final public static function columnsToArray(array $columns): array {
    $spec = [];
    foreach ($columns as $column) {
      $spec[$column->name] = self::columnToArray($column);
    }
    return $spec;
  }

  /**
   * Converts a Column object.
   *
   * @internal
   */
  final public static function columnToArray(Column $column): array {
    $spec = [];
    if ($column->type !== ColumnType::Undefined) {
      $spec['type'] = $column->type->value;
    }
    if ($column->description) {
      $spec['description'] = $column->description->value;
    }
    if ($column->serialize !== NULL) {
      $spec['serialize'] = $column->serialize;
    }
    if ($column->size !== ColumnSize::Undefined) {
      $spec['size'] = $column->size->value;
    }
    if ($column->notNull !== NULL) {
      $spec['not null'] = $column->notNull;
    }
    if ($column->default) {
      $spec['default'] = $column->default->value;
    }
    if ($column->length !== NULL) {
      $spec['length'] = $column->length;
    }
    if ($column->unsigned !== NULL) {
      $spec['unsigned'] = $column->unsigned;
    }
    if ($column->precision !== NULL) {
      $spec['precision'] = $column->precision;
    }
    if ($column->scale !== NULL) {
      $spec['scale'] = $column->scale;
    }
    if ($column->binary !== NULL) {
      $spec['binary'] = $column->binary;
    }
    if ($column->dbSpecificExtra !== NULL) {
      foreach ($column->dbSpecificExtra as $extra) {
        foreach ($extra as $key => $value) {
          $spec[$key] = $value;
        }
      }
    }
    return $spec;
  }

  /**
   * Converts an array of KeyColumn objects.
   *
   * @internal
   */
  final public static function keyColumnsToArray(array $columns): array {
    $spec = [];
    foreach ($columns as $column) {
      $spec[] = self::keyColumnToArray($column);
    }
    return $spec;
  }

  /**
   * Converts a KeyColumn object.
   *
   * @internal
   */
  final public static function keyColumnToArray(KeyColumn $column): string|array {
    if ($column->length !== NULL) {
      return [$column->name, $column->length];
    }
    return $column->name;
  }

  /**
   * Converts a PrimaryKey object.
   *
   * @internal
   */
  final public static function primaryKeyToArray(PrimaryKey $primaryKey): array {
    return self::keyColumnsToArray($primaryKey->columns);
  }

  /**
   * Converts an array of UniqueKey objects.
   *
   * @internal
   */
  final public static function uniqueKeysToArray(array $uniqueKeys): array {
    $spec = [];
    foreach ($uniqueKeys as $uniqueKey) {
      $spec[$uniqueKey->name] = self::keyColumnsToArray($uniqueKey->columns);
    }
    return $spec;
  }

  /**
   * Converts an array of Index objects.
   *
   * @internal
   */
  final public static function indexesToArray(array $indexes): array {
    $spec = [];
    foreach ($indexes as $index) {
      $spec[$index->name] = self::keyColumnsToArray($index->columns);
    }
    return $spec;
  }

  /**
   * Converts an array of ForeignKey objects.
   *
   * @internal
   */
  final public static function foreignKeysToArray(array $foreignKeys): array {
    $spec = [];
    foreach ($foreignKeys as $foreignKey) {
      $cols = self::keyColumnsToArray($foreignKey->columns);
      $foreignCols = self::keyColumnsToArray($foreignKey->foreignColumns);
      $match = [];
      for ($i = 0; $i < count($cols); $i++) {
        $match[$cols[$i]] = $foreignCols[$i];
      }
      $spec[$foreignKey->name] = [
        'table' => $foreignKey->foreignTable,
        'columns' => $match,
      ];
    }
    return $spec;
  }

}
