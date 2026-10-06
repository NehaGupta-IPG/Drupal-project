<?php

declare(strict_types=1);

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\ExceptionHandler as BaseExceptionHandler;

// cspell:ignore typname

/**
 * PostgreSQL database exception handler class.
 */
class ExceptionHandler extends BaseExceptionHandler {

  /**
   * {@inheritdoc}
   */
  public function isMissingTableException(\Throwable $exception): bool {
    $previous = $exception->getPrevious();
    return $previous instanceof \PDOException
      && ($previous->errorInfo[0] ?? $previous->getCode()) === '42P01';
  }

  /**
   * {@inheritdoc}
   */
  public function isTableCreationRace(\Throwable $exception): bool {
    $previous = $exception->getPrevious();
    if ($previous instanceof \PDOException) {
      $state = $previous->errorInfo[0] ?? $previous->getCode();
      // Concurrent CREATE TABLE statements can also collide on the table's
      // row type, either as a duplicate object or on the catalog's type-name
      // constraint, before the table is visible.
      if ($state === '42P07' || $state === '42710' || ($state === '23505' && str_contains($previous->getMessage(), 'pg_type_typname_nsp_index'))) {
        return TRUE;
      }
    }
    return parent::isTableCreationRace($exception);
  }

}
