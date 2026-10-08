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
   * Normalizes native SQLite errors from preparation or execution.
   */
  protected function rethrowNormalizedException(\Exception $exception, string $sql, array $arguments, array $options): never {
    if (!$exception instanceof \PDOException) {
      throw $exception;
    }

    $info = new SqlExecutionInfo($sql, $arguments, $options);
    $message = $info->appendToMessage($exception->getMessage());
    $code = (int) $exception->getCode();

    // Match all SQLSTATE 23xxx errors.
    if (substr($exception->getCode(), -6, -3) == '23') {
      throw new IntegrityConstraintViolationException($message, $code, $exception, $info);
    }

    // SQLite reports table errors with SQLITE_ERROR and specific messages.
    if (($exception->errorInfo[1] ?? NULL) === 1) {
      $detail = $exception->errorInfo[2] ?? '';
      if (str_starts_with($detail, 'no such table: ')) {
        throw new SchemaTableDoesNotExistException($message, $code, $exception, $info);
      }
      if (preg_match('/^table .+ already exists$/D', $detail) || str_starts_with($detail, 'there is already another table or index with this name: ')) {
        throw new SchemaTableAlreadyExistsException($message, $code, $exception, $info);
      }
    }

    throw new DatabaseExceptionWrapper($message, 0, $exception, $info);
  }

}
