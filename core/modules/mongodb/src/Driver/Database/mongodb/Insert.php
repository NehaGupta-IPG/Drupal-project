<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Query\Insert as QueryInsert;

/**
 * MongoDB implementation of \Drupal\Core\Database\Query\Insert.
 */
class Insert extends QueryInsert {

  use DocumentInsertTrait;

  /**
   * The MongoDB table information service.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   */
  protected $tableInformation;

  /**
   * {@inheritdoc}
   */
  public function __construct($connection, $table, array $options = []) {
    parent::__construct($connection, $table, $options);

    // We need the table information service that belongs to the current
    // connection.
    $this->tableInformation = $connection->tableInformation();
  }

  /**
   * Creates an object holding the data for an embedded table.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\EmbeddedTableData
   *   An object holding the data for an embedded table.
   */
  public function embeddedTableData(): EmbeddedTableData {
    return new EmbeddedTableData($this->connection, $this->table, $this->tableInformation);
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // If we're selecting from a SelectQuery, finish building the query and
    // pass it back, as any remaining options are irrelevant.
    if (!empty($this->fromQuery)) {
      $cursor = $this->fromQuery->execute();

      // Add the field names to $this->insertFields.
      $this->insertFields = $cursor->getFields();

      // Move the result from the SELECT query into $this->insertValues.
      $this->insertValues = $cursor->fetchAll(\PDO::FETCH_NUM);
    }

    // If validation fails, simply return NULL. Note that validation routines
    // in preExecute() may throw exceptions instead.
    if (!$this->preExecute()) {
      return NULL;
    }

    $prefixed_table = $this->connection->getPrefix() . $this->table;

    $last_insert_id = 0;

    $session = $this->connection->getMongodbSession();
    $session_started = FALSE;
    if (!$session->isInTransaction()) {
      $session->startTransaction();
      $session_started = TRUE;
    }
    foreach ($this->insertValues as $insert_values) {
      $insert_document = $this->getInsertDocumentForTable($this->table, $this->insertFields, $insert_values);

      // Get the last inserted ID.
      if ($auto_increment_field = $this->tableInformation->getTableAutoIncrementField($this->table)) {
        if (!empty($insert_document[$auto_increment_field])) {
          $last_insert_id = intval($insert_document[$auto_increment_field]);
        }
      }

      try {
        $this->connection->getConnection()->selectCollection($prefixed_table)->insertOne(
          $insert_document,
          [
            'session' => $this->connection->getMongodbSession(),
          ],
        );
      }
      catch (\Exception $e) {
        if ($session->isInTransaction() && $session_started) {
          $session->abortTransaction();
        }
        $this->connection->exceptionHandler()->handleExecutionException($e, NULL, $insert_values, $this->queryOptions);
      }
    }
    if ($session->isInTransaction() && $session_started) {
      $session->commitTransaction();
    }

    $this->insertValues = [];

    // SQL queries return string values.
    return (string) $last_insert_id;

  }

  /**
   * {@inheritdoc}
   */
  protected function preExecute() {
    // Do the data validation for the base table.
    $this->validateDataForTableInsert($this->table, $this->insertFields, $this->defaultFields, $this->insertValues);

    // Create an insert if there is no insert and the defaultFields are set.
    if (!isset($this->insertValues[0]) && count($this->defaultFields) > 0) {
      $this->insertValues[0] = [];
    }

    // If no values have been added, silently ignore this query. This can
    // happen if values are added conditionally, so we don't want to throw an
    // exception.
    if (!isset($this->insertValues[0]) && count($this->insertFields) > 0 && empty($this->fromQuery)) {
      return FALSE;
    }

    return TRUE;
  }

}
