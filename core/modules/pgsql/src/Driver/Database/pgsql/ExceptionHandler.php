<?php

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\Core\Database\Exception\SchemaTableDoesNotExistException;
use Drupal\Core\Database\ExceptionHandler as BaseExceptionHandler;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Database\StatementInterface;

/**
 * PostgreSQL database exception handler class.
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

    match ($exception->getCode()) {
      '42P01' =>  throw new SchemaTableDoesNotExistException($message, $code, $exception, $info),
      '42P07' =>  throw new SchemaTableAlreadyExistsException($message, $code, $exception, $info),
      default => throw new DatabaseExceptionWrapper($message, 0, $exception, $info),
    };
  }

}
