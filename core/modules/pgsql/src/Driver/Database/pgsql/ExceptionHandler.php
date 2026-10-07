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
 * PostgreSQL database exception handler.
 */
class ExceptionHandler extends BaseExceptionHandler {

  /**
   * {@inheritdoc}
   */
  public function handleStatementException(\Exception $exception, string $sql, array $options = []): never {
    $this->rethrowNormalizedException($exception, $sql, [], $options);
  }

  /**
   * {@inheritdoc}
   */
  public function handleExecutionException(\Exception $exception, StatementInterface $statement, array $arguments = [], array $options = []): never {
    if (!$exception instanceof \PDOException) {
      throw $exception;
    }
    $this->rethrowNormalizedException($exception, $statement->getQueryString(), $arguments, $options);
  }

  /**
   * Normalizes native PostgreSQL errors from preparation or execution.
   */
  protected function rethrowNormalizedException(\Exception $exception, string $sql, array $arguments, array $options): never {
    if (!$exception instanceof \PDOException) {
      throw $exception;
    }
    $info = new SqlExecutionInfo($sql, $arguments, $options);
    $message = $info->appendToMessage($exception->getMessage());
    $state = $exception->getCode();
    $code = (int) $state;
    if (str_starts_with((string) $state, '23')) {
      throw new IntegrityConstraintViolationException($message, $code, $exception, $info);
    }
    match ($state) {
      '42P01' => throw new SchemaTableDoesNotExistException($message, $code, $exception, $info),
      '42P07' => throw new SchemaTableAlreadyExistsException($message, $code, $exception, $info),
      default => throw new DatabaseExceptionWrapper($message, 0, $exception, $info),
    };
  }

}
