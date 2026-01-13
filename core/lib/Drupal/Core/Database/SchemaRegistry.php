<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
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
    string $schemaName,
    string $objectClass,
    string $objectName,
    string $infoQualifier,
    mixed $info,
    int $status,
  ): int {
    $try_again = FALSE;
    try {
      return $this->doInsert($schemaName, $objectClass, $objectName, $infoQualifier, $info, $status);
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
      return $this->doInsert($schemaName, $objectClass, $objectName, $infoQualifier, $info, $status);
    }
  }

  private function doInsert(
    string $schemaName,
    string $objectClass,
    string $objectName,
    string $infoQualifier,
    mixed $info,
    int $status,
  ): int {
    return $this->connection->insert(static::TABLE_NAME)
      ->fields([
        'schema_name' => $schemaName,
        'object_class' => $objectClass,
        'object_name' => $objectName,
        'info_qualifier' => $infoQualifier,
        'info' => serialize($info),
        'status' => $status,
      ])
      ->execute();
  }

  private function catchException(\Exception $e) {
    if ($this->connection->schema()->tableExists(static::TABLE_NAME)) {
      throw $e;
    }
  }

  private function ensureTableExists(): bool {
    try {
      $this->connection->schema()->createTableFromDefinition($this->schemaDefinition());
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
  private function schemaDefinition(): Table {
    return new Table(
      name: static::TABLE_NAME,
      description: 'Stores metadata information about the Drupal database schema implemented.',
      columns: [
        new Column(
          name: 'schema_name',
          description: 'The name of the Drupal schema implementing the db object.',
          type: ColumnType::VarcharAscii,
          length: 80,
          notNull: TRUE
        ),
        new Column(
          name: 'object_class',
          description: 'The Drupal class representing the db object.',
          type: ColumnType::VarcharAscii,
          length: 255,
          notNull: TRUE
        ),
        new Column(
          name: 'object_name',
          description: 'The Drupal name of the Drupal Schema implementing the db object.',
          type: ColumnType::VarcharAscii,
          length: 255,
          notNull: TRUE
        ),
        new Column(
          name: 'info_qualifier',
          description: 'The qualifier of the information stored in the row.',
          type: ColumnType::VarcharAscii,
          length: 80,
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
      primaryKey: new PrimaryKey(['schema_name', 'object_class', 'object_name', 'info_qualifier']),
    );
  }

}
