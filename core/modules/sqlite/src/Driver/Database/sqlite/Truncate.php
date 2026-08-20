<?php

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Query\Truncate as QueryTruncate;

// cSpell:ignore autoincrement

/**
 * SQLite implementation of \Drupal\Core\Database\Query\Truncate.
 *
 * SQLite doesn't support TRUNCATE, but a DELETE query with no condition has
 * exactly the effect (it is implemented by DROPing the table).
 *
 * Honors the "reset_serial_id" option by dropping the row "sqlite_sequence"
 * holds for the table. Emptying the table leaves that row behind. SQLite has
 * only DELETE, so the option set to FALSE costs nothing beyond it.
 */
class Truncate extends QueryTruncate {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!empty($this->queryOptions['reset_serial_id'])) {
      $this->resetSerialId();
    }
    parent::execute();
  }

  /**
   * Drops the AUTOINCREMENT high-water mark that sqlite_sequence holds.
   *
   * The row goes before the table is emptied. Emptying the table does not touch
   * sqlite_sequence, so the order does not change the outcome, and a failure
   * between the two leaves the table as it was instead of leaving it empty with
   * the next ID still ahead.
   */
  protected function resetSerialId(): void {
    // In this driver a prefix is an attached database, so the schema has to be
    // named explicitly instead of going through the query builder, which would
    // prefix the name of the internal table as if it were a Drupal one.
    $schema_name = rtrim($this->connection->getPrefix(), '.') ?: 'main';
    try {
      $this->connection->query("DELETE FROM [" . $schema_name . "].sqlite_sequence WHERE name = :name", [':name' => $this->table]);
    }
    catch (DatabaseExceptionWrapper $e) {
      // sqlite_sequence only exists once a table of this schema uses
      // AUTOINCREMENT, so it is missing while no table has a serial column. The
      // statement is attempted before checking that, so the common case spends
      // one query here instead of two. Anything else is a real failure.
      $sequence_exists = (bool) $this->connection->query("SELECT 1 FROM [" . $schema_name . "].sqlite_master WHERE type = :type AND name = :name", [
        ':type' => 'table',
        ':name' => 'sqlite_sequence',
      ])->fetchField();
      if ($sequence_exists) {
        throw $e;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    return $comments . 'DELETE FROM {' . $this->connection->escapeTable($this->table) . '} ';
  }

}
