<?php

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\Core\Database\Exception\SchemaTableDoesNotExistException;
use Drupal\Core\Database\ExceptionHandler as BaseExceptionHandler;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Database\StatementInterface;

/**
 * SQLite database exception handler class.
 */
class ExceptionHandler extends BaseExceptionHandler {

  /**
   * {@inheritdoc}
   */
  public function handleExecutionException(\Exception $exception, StatementInterface $statement, array $arguments = [], array $options = []): never {
    if (!$exception instanceof \PDOException) {
      throw $exception;
    }

    $info = new SqlExecutionInfo($statement->getQueryString(), $arguments, $options);
    $message = $info->appendToMessage($exception->getMessage());
    $code = (int) $exception->getCode();

    // Match all SQLSTATE 23xxx errors.
    if (substr($exception->getCode(), -6, -3) == '23') {
      throw new IntegrityConstraintViolationException($message, $code, $exception, $info);
    }

    if (str_contains($exception->getMessage(), 'no such table:')) {
      throw new SchemaTableDoesNotExistException($message, $code, $exception, $info);
    }

    if (str_contains($exception->getMessage(), 'already exists')) {
      throw new SchemaTableAlreadyExistsException($message, $code, $exception, $info);
    }

    throw new DatabaseExceptionWrapper($message, 0, $exception, $info);
  }

}
