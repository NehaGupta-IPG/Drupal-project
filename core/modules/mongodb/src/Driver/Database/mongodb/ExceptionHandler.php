<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\ExceptionHandler as BaseExceptionHandler;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\StatementInterface;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * MongoDB database exception handler class.
 */
class ExceptionHandler extends BaseExceptionHandler {

  /**
   * Handles exceptions thrown during execution of statement objects.
   *
   * @param \Exception $exception
   *   The exception to be handled.
   * @param \Drupal\Core\Database\StatementInterface|null $statement
   *   The statement object requested to be executed.
   * @param array $arguments
   *   An array of arguments for the prepared statement.
   * @param array $options
   *   An associative array of options to control how the database operation is
   *   run.
   *
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   */
  public function handleExecutionException(\Exception $exception, ?StatementInterface $statement, array $arguments = [], array $options = []): void {
    if ($exception instanceof BulkWriteException) {
      if ($exception->getCode() == 11000) {
        throw new IntegrityConstraintViolationException($exception->getMessage(), $exception->getCode(), $exception);
      }
      throw new DatabaseExceptionWrapper($exception->getMessage(), 0, $exception);
    }

    throw $exception;
  }

}
