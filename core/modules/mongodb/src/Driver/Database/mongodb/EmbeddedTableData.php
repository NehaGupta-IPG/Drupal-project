<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\InsertTrait;
use Drupal\Core\Database\SchemaException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;

/**
 * Class for creating embedded table data.
 */
class EmbeddedTableData {

  use InsertTrait;
  use DocumentInsertTrait;

  /**
   * The connection object on which to run this query.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The base table on which to add the embedded data.
   *
   * @var string
   */
  protected $baseTable;

  /**
   * The MongoDB table information service.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   */
  protected $tableInformation;

  /**
   * Whether to delete existing embedded table data on an update query.
   *
   * Can be "APPEND" or "REPLACE". Defaults to "REPLACE". This variable is not
   * used with inserts.
   *
   * @var bool
   */
  protected bool $deleteExistingDataOnUpdate;

  /**
   * Constructs a EmbeddedTableData object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A Connection object.
   * @param string $base_table
   *   The base table on which the embedded table data is stored.
   * @param \Drupal\mongodb\Driver\Database\mongodb\TableInformation $table_information
   *   The MongoDB table information service.
   * @param string $action
   *   Wether the embedded table data should be replace or append the existing
   *   data.
   */
  public function __construct(Connection $connection, $base_table, $table_information, $action = 'REPLACE') {
    $this->connection = $connection;
    $this->baseTable = $base_table;
    $this->tableInformation = $table_information;
    $this->deleteExistingDataOnUpdate = strtoupper($action) == 'APPEND' ? FALSE : TRUE;
  }

  /**
   * Compiles the embedded table data for saving to MongoDB.
   *
   * @param string $table
   *   The name of the table.
   *
   * @throws \Drupal\Core\Database\SchemaObjectDoesNotExistException
   *   If the specified base table doesn't exist.
   * @throws \Drupal\Core\Database\SchemaException
   *   If the specified parent table is not the parent table of the table or
   *   when the base table is not the base table of the table.
   */
  public function compile($table) {
    if (!$this->connection->schema()->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add embedded table data, because the table $table does not exist.");
    }

    if (!$this->connection->schema()->tableExists($this->baseTable)) {
      throw new SchemaObjectDoesNotExistException("Cannot add embedded table data, because the base table " . $this->baseTable . " does not exist.");
    }

    if ($this->baseTable != $this->tableInformation->getTableBaseTable($table)) {
      throw new SchemaException("Cannot add embedded table data, because the table $table is not an embedded table of base table " . $this->baseTable . '.');
    }

    // Do the data validation for the embedded table.
    $this->validateDataForTableInsert($table, $this->insertFields, $this->defaultFields, $this->insertValues);

    $document = [];
    foreach ($this->insertValues as $insert_values) {
      $document[] = $this->getInsertDocumentForTable($table, $this->insertFields, $insert_values);
    }

    return $document;
  }

  /**
   * Whether to delete the existing embedded table data on an update query.
   *
   * @return bool
   *   Whether or not to delete the existing embedded table data.
   */
  public function deleteExistingDataOnUpdate() {
    return $this->deleteExistingDataOnUpdate;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInsertPlaceholderFragment(array $nested_insert_values, array $default_fields) {
    // Do not use this method. This method is only useful for SQL databases.
    throw new MongodbSQLException();
  }

}
