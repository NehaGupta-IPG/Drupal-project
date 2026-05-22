<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Query\NoUniqueFieldException;
use Drupal\Core\Database\Query\Upsert as QueryUpsert;
use MongoDB\UpdateResult;

/**
 * The MongoDB implementation of \Drupal\Core\Database\Query\Upsert.
 */
class Upsert extends QueryUpsert {

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
  protected function preExecute() {
    // Do the data validation for the base table.
    $this->validateDataForTableInsert($this->table, $this->insertFields, $this->defaultFields, $this->insertValues);

    // Confirm that the user set the unique/primary key of the table.
    if (!$this->key) {
      throw new NoUniqueFieldException('There is no unique field specified.');
    }

    return isset($this->insertValues[0]) || $this->insertFields;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if (!$this->preExecute()) {
      return NULL;
    }

    $prefixed_table = $this->connection->getPrefix() . $this->table;

    $affected_rows = 0;
    foreach ($this->insertValues as $insert_values) {
      $insert_document = $this->getInsertDocumentForTable($this->table, $this->insertFields, $insert_values);

      if (isset($insert_document[$this->key])) {
        // Create the update filter and remove the filter key from the
        // insert_document.
        $insert_filter = [];
        $insert_filter[$this->key] = $insert_document[$this->key];
        unset($insert_document[$this->key]);

        // Make sure that values that are set to NULL are unset.
        $unset_document = [];
        foreach ($this->insertFields as $insert_field) {
          if (!isset($insert_document[$insert_field]) && ($insert_field != $this->key)) {
            $unset_document[$insert_field] = NULL;
          }
        }

        $result = NULL;
        if (!empty($insert_document) && !empty($unset_document)) {
          $result = $this->connection->getConnection()->selectCollection($prefixed_table)->updateOne(
            $insert_filter,
            [
              '$set' => $insert_document,
              '$unset' => $unset_document,
            ],
            [
              'upsert' => TRUE,
              'session' => $this->connection->getMongodbSession(),
            ],
          );
        }
        elseif (!empty($insert_document)) {
          $result = $this->connection->getConnection()->selectCollection($prefixed_table)->updateOne(
            $insert_filter,
            [
              '$set' => $insert_document,
            ],
            [
              'upsert' => TRUE,
              'session' => $this->connection->getMongodbSession(),
            ],
          );
        }
        elseif (!empty($unset_document)) {
          $result = $this->connection->getConnection()->selectCollection($prefixed_table)->updateOne(
            $insert_filter,
            [
              '$unset' => $unset_document,
            ],
            [
              'session' => $this->connection->getMongodbSession(),
              'upsert' => TRUE,
            ],
          );
        }

        if ($result instanceof UpdateResult) {
          $affected_rows += $result->getModifiedCount() + $result->getUpsertedCount();
        }
      }
    }

    // Re-initialize the values array so that we can re-use this query.
    $this->insertValues = [];

    return $affected_rows;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    // Nothing to do.
    return '';
  }

}
