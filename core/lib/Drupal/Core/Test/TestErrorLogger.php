<?php

namespace Drupal\Core\Test;

use Drupal\Core\Test\Exception\TestErrorLoggerIgnoreException;
use Drupal\Core\Utility\Error;

/**
 * Logs serialized test data to a file with symlink-based locking.
 *
 * This provides a file-based logging mechanism for test environments. Atomic
 * operations are ensured by using symlinks as locks.
 */
final class TestErrorLogger {

  /**
   * The maximum time to wait for a lock in microseconds.
   */
  protected const MAX_LOCK_WAIT_TIME = 1000000;

  /**
   * Private constructor to prevent instantiation.
   */
  private function __construct() {
  }

  /**
   * Appends a serialized error to the log file.
   *
   * @param \Drupal\Core\Test\TestDatabase $test_db
   *   The test database object to provide the log file path.
   * @param string $message
   *   The error message.
   * @param string $type
   *   The error type.
   * @param string $function
   *   The function that triggered the error.
   * @param string $file
   *   The file where the error occurred.
   * @param int $line
   *   The line number where the error occurred.
   * @param \Throwable|null $exception
   *   The exception that triggered the error, or NULL if the error was not
   *   triggered by an exception.
   */
  public static function writeError(TestDatabase $test_db, string $message, string $type, string $function, string $file, int $line, ?\Throwable $exception): void {
    if ($exception instanceof TestErrorLoggerIgnoreException || $exception === NULL && str_starts_with($message, TestErrorLoggerIgnoreException::class)) {
      return;
    }
    $assertion = [
      $message,
      $type,
      [
        'function' => $function,
        'file' => $file,
        'line' => $line,
      ],
    ];
    $log_file = $test_db->getTestErrorLoggerPath();
    $lock = static::acquireLock($log_file);
    try {
      file_put_contents($log_file, rawurlencode(serialize($assertion)) . "\n", FILE_APPEND);
    }
    finally {
      static::releaseLock($lock);
    }
  }

  /**
   * Reads all logged entries, clears the log, and re-triggers the errors.
   *
   * Deprecations are re-triggered via trigger_error() so they can be collected
   * by PHPUnit. All other error types throw an exception so they can be tested
   * by PHPUnit.
   *
   * @param \Drupal\Core\Test\TestDatabase $test_db
   *   The test database object to provide the log file path.
   *
   * @throws \Exception
   *   If a non-deprecation error is found in the log.
   */
  public static function reTriggerErrors(TestDatabase $test_db): void {
    $log_file = $test_db->getTestErrorLoggerPath();
    $lock = static::acquireLock($log_file);
    try {
      if (!file_exists($log_file)) {
        return;
      }
      $content = file_get_contents($log_file);
      unlink($log_file);
      if ($content === '' || $content === FALSE) {
        return;
      }
    }
    finally {
      static::releaseLock($lock);
    }
    foreach (explode("\n", trim($content)) as $line) {
      $parameters = unserialize(rawurldecode($line), ['allowed_classes' => FALSE]);
      if (count($parameters) === 3) {
        if ($parameters[1] === 'User deprecated function') {
          // Fire the same deprecation message to allow it to be
          // collected by PHPUnit.
          // phpcs:ignore Drupal.Semantics.FunctionTriggerError
          @trigger_error((string) $parameters[0], E_USER_DEPRECATED);
        }
        else {
          throw new \Exception($parameters[1] . ': ' . $parameters[0] . "\n" . Error::formatBacktrace([$parameters[2]]));
        }
      }
      else {
        throw new \Exception('Error thrown with the wrong amount of parameters.');
      }
    }
  }

  /**
   * Acquires a symlink-based lock, waiting if necessary.
   *
   * Uses symlinks for atomic locking. If the lock is held by another process,
   * this method will wait with exponential backoff up to a maximum of 1 second
   * before throwing a \RuntimeException.
   *
   * @param string $log_file
   *   The log file path. The lock symlink is derived by appending '.lock'.
   *
   * @return string
   *   The path to the lock file.
   *
   * @throws \RuntimeException
   *   If the lock cannot be acquired within 1 second.
   */
  private static function acquireLock(string $log_file): string {
    $lock_file = $log_file . '.lock';
    if (@symlink(__FILE__, $lock_file) !== FALSE) {
      return $lock_file;
    }
    $delay = 10000;
    $total = 0;
    do {
      usleep($delay);
      if (@symlink(__FILE__, $lock_file) !== FALSE) {
        return $lock_file;
      }
      $total += $delay;
      $delay *= 2;
    } while ($total < self::MAX_LOCK_WAIT_TIME);
    throw new \RuntimeException('Failed to acquire lock file: ' . $lock_file);
  }

  /**
   * Releases the symlink-based lock.
   *
   * @param string $lock_file
   *   The lock file path.
   */
  private static function releaseLock(string $lock_file): void {
    unlink($lock_file);
  }

}
