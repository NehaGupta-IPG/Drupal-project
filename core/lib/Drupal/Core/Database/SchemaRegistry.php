<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;

/**
 * @internal
 */
final class SchemaRegistry {

  /**
   * The table name.
   */
  private const TABLE_NAME = 'schema_registry';

  public function __construct(
    protected readonly Connection $connection,
  ) {
  }

  public function register(
    SchemaDefinitionType $schemaType,
    string $schemaName,
    DatabaseObjectType $objectType,
    string $objectName,
    string $infoQualifier,
    mixed $info,
    int $status,
  ): int {
    if ($schemaType === SchemaDefinitionType::Storage && $schemaName === 'schema_definition' && $objectType === DatabaseObjectType::Table && $objectName === static::TABLE_NAME) {
      return 0;
    }
    $try_again = FALSE;
    try {
      return $this->doInsert($schemaType->value, $schemaName, $objectType->value, $objectName, $infoQualifier, $info, $status);
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the table.
      if (!$try_again = $this->ensureTableExists()) {
        // If the exception happened for other reason than the missing table,
        // propagate the exception.
        throw $e;
      }
    }
    // Now that the table has been created, try again if necessary.
    if ($try_again) {
      return $this->doInsert($schemaType->value, $schemaName, $objectType->value, $objectName, $infoQualifier, $info, $status);
    }
  }

  private function doInsert(
    string $schemaType,
    string $schemaName,
    string $objectType,
    string $objectName,
    string $infoQualifier,
    mixed $info,
    int $status,
  ): int {
    return $this->connection->insert(static::TABLE_NAME)
      ->fields([
        'schema_type' => $schemaType,
        'schema_name' => $schemaName,
        'object_type' => $objectType,
        'object_name' => $objectName,
        'info_qualifier' => $infoQualifier,
        'info' => serialize($info),
        'status' => $status,
      ])
      ->execute();
  }

  public function registerUpdate(
    SchemaDefinitionType $schemaType,
    string $schemaName,
    DatabaseObjectType $objectType,
    string $objectName,
    string $infoQualifier,
    mixed $info,
    int $status,
  ): int {
    $exist = $this->connection->select(static::TABLE_NAME, 'r')
      ->fields('r', ['object_name'])
      ->condition('object_type', $objectType->value)
      ->condition('object_name', $objectName)
      ->condition('info_qualifier', $infoQualifier)
      ->execute()
      ->fetchField();

    if ($exist) {
      $this->connection->update(static::TABLE_NAME)
        ->fields([
          'schema_type' => $schemaType->value,
          'schema_name' => $schemaName,
          'info' => serialize($info),
          'status' => $status,
        ])
        ->condition('object_type', $objectType->value)
        ->condition('object_name', $objectName)
        ->condition('info_qualifier', $infoQualifier)
        ->execute();
    }
    else {
      return $this->connection->insert(static::TABLE_NAME)
        ->fields([
          'schema_type' => $schemaType->value,
          'schema_name' => $schemaName,
          'object_type' => $objectType->value,
          'object_name' => $objectName,
          'info_qualifier' => $infoQualifier,
          'info' => serialize($info),
          'status' => $status,
        ])
        ->execute();
    }

    return 0;
  }

  private function catchException(\Exception $e): void {
    if ($this->connection->schema()->tableExists(static::TABLE_NAME)) {
      throw $e;
    }
  }

  private function ensureTableExists(): bool {
    try {
      $this->connection->schema()->createSchemaFromDefinition($this->schemaDefinition());
    }
    catch (DatabaseException) {
    }
    catch (\Exception) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Defines the schema for the registry table.
   *
   * @internal
   */
  private function schemaDefinition(): Schema {
    $tables[] = new Table(
      name: static::TABLE_NAME,
      description: 'Stores metadata information about the Drupal database schema implemented.',
      columns: [
        new Column(
          name: 'schema_type',
          description: 'The Drupal schema type implementing the db object.',
          type: ColumnType::VarcharAscii,
          length: 30,
          notNull: TRUE
        ),
        new Column(
          name: 'schema_name',
          description: 'The name of the Drupal schema implementing the db object.',
          type: ColumnType::VarcharAscii,
          length: 80,
          notNull: TRUE
        ),
        new Column(
          name: 'object_type',
          description: 'The db object type.',
          type: ColumnType::VarcharAscii,
          length: 30,
          notNull: TRUE
        ),
        new Column(
          name: 'object_name',
          description: 'The Drupal name of the db object.',
          type: ColumnType::VarcharAscii,
          length: 255,
          notNull: TRUE
        ),
        new Column(
          name: 'info_qualifier',
          description: 'The qualifier of the information stored in the row.',
          type: ColumnType::VarcharAscii,
          length: 30,
          notNull: TRUE
        ),
        new Column(
          name: 'info',
          description: 'The registered information.',
          type: ColumnType::Blob,
          notNull: TRUE,
        ),
        new Column(
          name: 'status',
          description: 'The ...',
          type: ColumnType::Int,
          notNull: TRUE,
          default: new IntValue(0),
        ),
      ],
      primaryKey: new PrimaryKey(['object_type', 'object_name', 'info_qualifier']),
    );

    return new Schema(
      type: SchemaDefinitionType::Storage,
      name: 'schema_definition',
      tables: $tables,
    );
  }

}
