<?php

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Query\Upsert as QueryUpsert;

/**
 * MySQL implementation of \Drupal\Core\Database\Query\Upsert.
 */
class Upsert extends QueryUpsert {

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // Default fields are always placed first for consistency.
    $insert_fields = array_merge($this->defaultFields, $this->insertFields);
    $insert_fields = array_combine($insert_fields, $insert_fields);
    $insert_fields = array_map(function ($field) {
      return $this->connection->escapeField($field);
    }, $insert_fields);

    $query = $comments . 'INSERT INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') VALUES ';

    $values = $this->getInsertPlaceholderFragment($this->insertValues, $this->defaultFields);
    $query .= implode(', ', $values);

    // The VALUES() function, used to refer to the new row in the ON DUPLICATE
    // KEY UPDATE clause, was deprecated in MySQL 8.0.20 and emits a warning on
    // every upsert. MySQL 8.0.19 introduced a row alias as the recommended
    // replacement. MariaDB and MySQL versions before 8.0.19 do not support the
    // alias syntax, so they continue to use VALUES(). Unlike PostgreSQL's
    // EXCLUDED, the MySQL alias is a plain identifier that shares a namespace
    // with the table's columns, so a deliberately unlikely name is used to
    // avoid colliding with a real column.
    // @see https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
    $use_alias = !$this->connection->isMariaDb() && version_compare($this->connection->version(), '8.0.19', '>=');
    if ($use_alias) {
      $query .= ' AS drupal_upsert_alias';
    }

    // Updating the unique / primary key fields is not necessary.
    foreach ($this->key as $key) {
      unset($insert_fields[$key]);
    }

    $update = [];
    foreach ($insert_fields as $field) {
      $update[] = $use_alias ? "$field = drupal_upsert_alias.$field" : "$field = VALUES($field)";
    }

    $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update);

    return $query;
  }

}
