<?php

namespace Drupal\Core;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;

/**
 * Trait for classes that require database storage.
 */
trait DatabaseStorageSchemaTrait {

  /**
   * Acts on an exception.
   *
   * @param string $table
   *   The table name.
   * @param \Exception $e
   *   The exception.
   *
   * @throws \Exception
   */
  protected function catchException(string $table, \Exception $e): void {
    if ($this->connection->schema()->tableExists($table)) {
      throw $e;
    }
  }

  /**
   * Checks if the schema exists and create it if not.
   *
   * @return bool
   *   TRUE if the schema was created, FALSE otherwise.
   */
  protected function ensureSchemaStorage(): bool {
    // If another process has already created a table, attempting to recreate
    // it will throw an exception. In this case just catch the exception and do
    // nothing. Other exceptions let the method return FALSE.
    try {
      $schemaDefinition = $this->schemaDefinition();
      assert($schemaDefinition->type === SchemaDefinitionType::Storage, 'Invalid schema definition type, must be SchemaDefinitionType::Storage');
      $this->connection->schema()->createSchemaFromDefinition($schemaDefinition);
    }
    catch (DatabaseException) {
    }
    catch (\Exception) {
      return FALSE;
    }
    return TRUE;
  }

}
