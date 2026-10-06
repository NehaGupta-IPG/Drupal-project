<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\ExceptionHandler as BaseExceptionHandler;

/**
 * SQLite database exception handler class.
 */
class ExceptionHandler extends BaseExceptionHandler {

  /**
   * {@inheritdoc}
   */
  public function isMissingTableException(\Throwable $exception): bool {
    $previous = $exception->getPrevious();
    // SQLite uses HY000/1 for many errors, including missing tables.
    return $previous instanceof \PDOException
      && ($previous->errorInfo[0] ?? $previous->getCode()) === 'HY000'
      && ($previous->errorInfo[1] ?? NULL) === 1
      && str_contains($previous->getMessage(), 'no such table:');
  }

  /**
   * {@inheritdoc}
   */
  public function isTableCreationRace(\Throwable $exception): bool {
    $previous = $exception->getPrevious();
    // SQLite reports both syntax and duplicate table errors as HY000/1.
    return parent::isTableCreationRace($exception)
      || ($previous instanceof \PDOException
        && ($previous->errorInfo[0] ?? $previous->getCode()) === 'HY000'
        && ($previous->errorInfo[1] ?? NULL) === 1
        && str_contains($previous->getMessage(), 'already exists'));
  }

}
