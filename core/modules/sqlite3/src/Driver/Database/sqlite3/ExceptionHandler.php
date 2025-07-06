<?php

namespace Drupal\sqlite3\Driver\Database\sqlite3;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\ExceptionHandler as BaseExceptionHandler;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\StatementInterface;

/**
 * SQLite3 database exception handler class.
 */
class ExceptionHandler extends BaseExceptionHandler {

  /**
   * {@inheritdoc}
   */
  public function handleStatementException(\Exception $exception, string $sql, array $options = []): void {
    if (!$exception instanceof \SQLite3Exception) {
        throw $exception;
    }
    // Wrap the exception in another exception, because PHP does not allow
    // overriding Exception::getMessage(). Its message is the extra database
    // debug information.
    $message = $exception->getMessage() . ": " . $sql . "; ";
    throw new DatabaseExceptionWrapper($message, 0, $exception);
  }

  /**
   * {@inheritdoc}
   */
  public function handleExecutionException(\Exception $exception, StatementInterface $statement, array $arguments = [], array $options = []): void {
    if (!$exception instanceof \SQLite3Exception) {
        throw $exception;
    }
    // Wrap the exception in another exception, because PHP does not allow
    // overriding Exception::getMessage(). Its message is the extra database
    // debug information.
    $message = $exception->getMessage() . ": " . $statement->getQueryString() . "; " . print_r($arguments, TRUE);
    // Match all SQLSTATE 23xxx errors.
    if (substr($exception->getCode(), -6, -3) == '23') {
      throw new IntegrityConstraintViolationException($message, $exception->getCode(), $exception);
    }
    throw new DatabaseExceptionWrapper($message, 0, $exception);
  }

}
