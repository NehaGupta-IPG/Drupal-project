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
   * @param array<int|string,\Drupal\Core\Database\SchemaDefinition\Table|<array<string,mixed>>> $schema
   *   An array of mixed Table objects or schema definition structure arrays.
   *
   * @return array<string<array<string,mixed>>>
   *   A schema definition structure array.
   *
   * @internal
   */
  final public static function schemaToArray(array $schema): array {
    $spec = [];
    foreach ($schema as $name => $table) {
      if ($table instanceof Table) {
        $spec[$table->name] = self::tableToArray($table);
      }
      else {
        $spec[$name] = $table;
      }
    }
    return $spec;
  }

  /**
   * Converts a Table object.
   *
   * @param \Drupal\Core\Database\SchemaDefinition\Table $table
   *   A Table object.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
   *
   * @internal
   */
  final public static function tableToArray(Table $table): array {
    $spec = [];
    if ($table->description !== NULL) {
      $spec['description'] = $table->description;
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
   * @param list<\Drupal\Core\Database\SchemaDefinition\Column> $columns
   *   An array of Column objects.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
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
   * @param \Drupal\Core\Database\SchemaDefinition\Column $column
   *   An Column object.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
   *
   * @internal
   */
  final public static function columnToArray(Column $column): array {
    $spec = [];
    if ($column->type) {
      $spec['type'] = $column->type->value;
    }
    if ($column->description !== NULL) {
      $spec['description'] = $column->description;
    }
    if ($column->serialize !== NULL) {
      $spec['serialize'] = $column->serialize;
    }
    if ($column->size) {
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
   * @param list<\Drupal\Core\Database\SchemaDefinition\KeyColumn> $columns
   *   An array of KeyColumn objects.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
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
   * @param \Drupal\Core\Database\SchemaDefinition\KeyColumn $column
   *   An KeyColumn object.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
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
   * @param \Drupal\Core\Database\SchemaDefinition\PrimaryKey $primaryKey
   *   An PrimaryKey object.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
   *
   * @internal
   */
  final public static function primaryKeyToArray(PrimaryKey $primaryKey): array {
    return self::keyColumnsToArray($primaryKey->columns);
  }

  /**
   * Converts an array of UniqueKey objects.
   *
   * @param list<\Drupal\Core\Database\SchemaDefinition\UniqueKey> $uniqueKeys
   *   An array of UniqueKey objects.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
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
   * @param list<\Drupal\Core\Database\SchemaDefinition\Index> $indexes
   *   An array of Index objects.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
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
   * @param list<\Drupal\Core\Database\SchemaDefinition\ForeignKey> $foreignKeys
   *   An array of ForeignKey objects.
   *
   * @return array<string,mixed>
   *   A schema definition structure array.
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
