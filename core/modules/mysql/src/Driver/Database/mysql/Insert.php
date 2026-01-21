<?php

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\mysql\Driver\Database\mysql\Insert as MysqlInsert;

@trigger_error('\Drupal\Core\Database\Driver\mysql\Insert is deprecated in drupal:9.4.0 and is removed from drupal:11.0.0. The MySQL database driver has been moved to the mysql module. See https://www.drupal.org/node/3129492', E_USER_DEPRECATED);

/**
 * MySQL implementation of \Drupal\Core\Database\Query\Insert.
 *
 * @deprecated in drupal:9.4.0 and is removed from drupal:11.0.0. The MySQL
 *   database driver has been moved to the mysql module.
 *
 * @see https://www.drupal.org/node/3129492
 */
class Insert extends QueryInsert {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if (!$this->preExecute()) {
      return NULL;
    }

    // If we're selecting from a SelectQuery, finish building the query and
    // pass it back, as any remaining options are irrelevant.
    if (empty($this->fromQuery)) {
      $max_placeholder = 0;
      $values = [];
      foreach ($this->insertValues as $insert_values) {
        foreach ($insert_values as $value) {
          $values[':db_insert_placeholder_' . $max_placeholder++] = $value;
        }
      }
    }
    else {
      $values = $this->fromQuery->getArguments();
    }

    $stmt = $this->connection->prepareStatement((string) $this, $this->queryOptions);
    try {
      $stmt->execute($values, $this->queryOptions);
      $last_insert_id = $this->connection->lastInsertId();
    }
    catch (\Exception $e) {
      $this->connection->exceptionHandler()->handleExecutionException($e, $stmt, $values, $this->queryOptions);
    }

    // Re-initialize the values array so that we can re-use this query.
    $this->insertValues = [];

    return $last_insert_id;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // Default fields are always placed first for consistency.
    $insert_fields = array_merge($this->defaultFields, $this->insertFields);
    $insert_fields = array_map(function ($field) {
      return $this->connection->escapeField($field);
    }, $insert_fields);

    // If we're selecting from a SelectQuery, finish building the query and
    // pass it back, as any remaining options are irrelevant.
    if (!empty($this->fromQuery)) {
      $insert_fields_string = $insert_fields ? ' (' . implode(', ', $insert_fields) . ') ' : ' ';
      return $comments . 'INSERT INTO {' . $this->table . '}' . $insert_fields_string . $this->fromQuery;
    }

    $query = $comments . 'INSERT INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') VALUES ';

    $values = $this->getInsertPlaceholderFragment($this->insertValues, $this->defaultFields);
    $query .= implode(', ', $values);

    return $query;
  }

}
