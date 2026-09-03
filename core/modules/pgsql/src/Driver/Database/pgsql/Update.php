<?php

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Query\Update as QueryUpdate;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * PostgreSQL implementation of \Drupal\Core\Database\Query\Update.
 */
class Update extends QueryUpdate {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $max_placeholder = 0;
    $blobs = [];
    $blob_count = 0;
    // The values are bound on the client statement, which does not report them
    // back once execution fails. They are collected here so that the exception
    // handler can carry them, like the drivers that pass an argument array to
    // \Drupal\Core\Database\StatementInterface::execute() do.
    $placeholders = [];

    // Because we filter $fields the same way here and in __toString(), the
    // placeholders will all match up properly.
    $stmt = $this->connection->prepareStatement((string) $this, $this->queryOptions, TRUE);

    // Fetch the list of blobs and sequences used on that table.
    $table_information = $this->connection->schema()->queryTableInformation($this->table);

    // Expressions take priority over literal fields, so we process those first
    // and remove any literal fields that conflict.
    $fields = $this->fields;
    foreach ($this->expressionFields as $field => $data) {
      if (!empty($data['arguments'])) {
        foreach ($data['arguments'] as $placeholder => $argument) {
          // We assume that an expression will never happen on a BLOB field,
          // which is a fairly safe assumption to make since in most cases
          // it would be an invalid query anyway.
          $stmt->getClientStatement()->bindParam($placeholder, $data['arguments'][$placeholder]);
          $placeholders[$placeholder] = $argument;
        }
      }
      if ($data['expression'] instanceof SelectInterface) {
        $data['expression']->compile($this->connection, $this);
        $select_query_arguments = $data['expression']->arguments();
        foreach ($select_query_arguments as $placeholder => $argument) {
          $stmt->getClientStatement()->bindParam($placeholder, $select_query_arguments[$placeholder]);
          $placeholders[$placeholder] = $argument;
        }
      }
      unset($fields[$field]);
    }

    foreach ($fields as $field => $value) {
      $placeholder = ':db_update_placeholder_' . ($max_placeholder++);
      $placeholders[$placeholder] = $value;

      if (isset($table_information->blob_fields[$field]) && $value !== NULL) {
        $blobs[$blob_count] = fopen('php://memory', 'a');
        fwrite($blobs[$blob_count], $value);
        rewind($blobs[$blob_count]);
        $stmt->getClientStatement()->bindParam($placeholder, $blobs[$blob_count], \PDO::PARAM_LOB);
        ++$blob_count;
      }
      else {
        $stmt->getClientStatement()->bindParam($placeholder, $fields[$field]);
      }
    }

    if (count($this->condition)) {
      $this->condition->compile($this->connection, $this);

      $arguments = $this->condition->arguments();
      foreach ($arguments as $placeholder => $value) {
        $stmt->getClientStatement()->bindParam($placeholder, $arguments[$placeholder]);
        $placeholders[$placeholder] = $value;
      }
    }

    if ($this->connection->inTransaction()) {
      $savepoint = $this->connection->startTransaction('mimic_implicit_commit');
    }
    try {
      $stmt->execute(NULL, $this->queryOptions);
      if (isset($savepoint)) {
        $savepoint->commitOrRelease();
      }
      return $stmt->rowCount();
    }
    catch (\Exception $e) {
      if (isset($savepoint)) {
        $savepoint->rollback();
      }
      $this->connection->exceptionHandler()->handleExecutionException($e, $stmt, $placeholders, $this->queryOptions);
    }
  }

}
