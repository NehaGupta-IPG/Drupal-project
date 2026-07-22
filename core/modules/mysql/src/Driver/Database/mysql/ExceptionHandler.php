<?php

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\DatabaseExceptionInfo;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\ExceptionHandler as BaseExceptionHandler;
use Drupal\Core\Database\Exception\SchemaPrimaryKeyMustBeDroppedException;
use Drupal\Core\Database\Exception\SchemaTableColumnSizeTooLargeException;
use Drupal\Core\Database\Exception\SchemaTableKeyTooLargeException;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\StatementInterface;

/**
 * MySql database exception handler class.
 */
class ExceptionHandler extends BaseExceptionHandler {

  /**
   * {@inheritdoc}
   */
  public function handleExecutionException(\Exception $exception, StatementInterface $statement, array $arguments = [], array $options = []): never {
    if (!$exception instanceof \PDOException) {
      throw $exception;
    }
    $this->rethrowNormalizedException($exception, $exception->getCode(), $exception->errorInfo[1] ?? NULL, $statement->getQueryString(), $arguments, $options);
  }

  /**
   * Rethrows exceptions thrown during execution of statement objects.
   *
   * Wrap the exception in another exception, because PHP does not allow
   * overriding Exception::getMessage(). Its message is the extra database
   * debug information.
   *
   * @param \Exception $exception
   *   The exception to be handled.
   * @param int|string $sqlState
   *   MySql SQLState error condition.
   * @param int|null $errorCode
   *   MySql error code.
   * @param string $queryString
   *   The SQL statement string.
   * @param array $arguments
   *   An array of arguments for the prepared statement.
   * @param array $options
   *   An associative array of options to control how the database operation is
   *   run.
   *
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   * @throws \Drupal\Core\Database\Exception\SchemaPrimaryKeyMustBeDroppedException
   * @throws \Drupal\Core\Database\Exception\SchemaTableColumnSizeTooLargeException
   * @throws \Drupal\Core\Database\Exception\SchemaTableKeyTooLargeException
   */
  protected function rethrowNormalizedException(
    \Exception $exception,
    int|string $sqlState,
    ?int $errorCode,
    string $queryString,
    array $arguments,
  ): never {

    // SQLState could be 'HY000' which cannot be used as a $code argument for
    // exceptions. PDOException is contravariant in this case, but since we are
    // re-throwing an exception that inherits from \Exception, we need to
    // convert the code to an integer.
    // @see https://www.php.net/manual/en/class.exception.php
    // @see https://www.php.net/manual/en/class.pdoexception.php
    $code = (int) $sqlState;

    $info = new DatabaseExceptionInfo($queryString, $arguments, $options);

    // If a max_allowed_packet error occurs the message length is truncated.
    // This should prevent the error from recurring if the exception is logged
    // to the database using dblog or the like.
    if ($errorCode === 1153) {
      $message = Unicode::truncateBytes($exception->getMessage(), Connection::MIN_MAX_ALLOWED_PACKET);
      throw new DatabaseExceptionWrapper($message, $code, $exception, $info);
    }

    $message = $exception->getMessage();

    // SQLSTATE 23xxx errors indicate an integrity constraint violation. Also,
    // in case of attempted INSERT of a record with an undefined column and no
    // default value indicated in schema, MySql returns a 1364 error code.
    if (substr($sqlState, -6, -3) == '23' || $errorCode === 1364) {
      throw new IntegrityConstraintViolationException($message, $code, $exception, $info);
    }

    match ($sqlState) {
      'HY000' =>  match ($errorCode) {
        4111 => throw new SchemaPrimaryKeyMustBeDroppedException($message, 0, $exception, $info),
        default => throw new DatabaseExceptionWrapper($message, 0, $exception, $info),
      },
      '42000' =>  match ($errorCode) {
        1071 => throw new SchemaTableKeyTooLargeException($message, $code, $exception, $info),
        1074 => throw new SchemaTableColumnSizeTooLargeException($message, $code, $exception, $info),
        default => throw new DatabaseExceptionWrapper($message, 0, $exception, $info),
      },
      default => throw new DatabaseExceptionWrapper($message, 0, $exception, $info),
    };
  }

}
