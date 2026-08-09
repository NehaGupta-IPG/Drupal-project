<?php

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\Query\InvalidMergeQueryException;
use Drupal\Core\Database\Query\Merge as QueryMerge;

/**
 * @addtogroup database
 * @{
 */

/**
 * PostgreSQL implementation of \Drupal\Core\Database\Query\Merge.
 */
class Merge extends QueryMerge {

  /**
   * {@inheritdoc}
   *
   * The insert can fail when a racing insert beat us in inserting the same
   * row, which the catch below recovers from. On PostgreSQL a failed
   * statement aborts the whole transaction, so an enclosing transaction must
   * be protected with a savepoint for the recovery to work.
   */
  public function execute() {
    if (!count($this->condition)) {
      throw new InvalidMergeQueryException('Invalid merge query: no conditions');
    }

    $select = $this->connection->select($this->conditionTable)
      ->condition($this->condition);
    $select->addExpression('1');

    if (!$select->execute()->fetchField()) {
      $savepoint = $this->connection->inTransaction() ? $this->connection->startTransaction() : NULL;
      try {
        $insert = $this->connection->insert($this->table)->fields($this->insertFields);
        if ($this->defaultFields) {
          $insert->useDefaults($this->defaultFields);
        }
        $insert->execute();
        $savepoint?->commitOrRelease();
        return self::STATUS_INSERT;
      }
      catch (IntegrityConstraintViolationException $e) {
        $savepoint?->rollBack();
        // The insert query failed, maybe it's because a racing insert query
        // beat us in inserting the same row. Retry the select query, if it
        // returns a row, ignore the error and continue with the update
        // query below.
        if (!$select->execute()->fetchField()) {
          throw $e;
        }
      }
      catch (\Exception $e) {
        $savepoint?->rollBack();
        throw $e;
      }
    }

    if ($this->needsUpdate) {
      $update = $this->connection->update($this->table)
        ->fields($this->updateFields)
        ->condition($this->condition);
      if ($this->expressionFields) {
        foreach ($this->expressionFields as $field => $data) {
          $update->expression($field, $data['expression'], $data['arguments']);
        }
      }
      $update->execute();
      return self::STATUS_UPDATE;
    }
    return NULL;
  }

}

/**
 * @} End of "addtogroup database".
 */
