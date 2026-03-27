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
   * The name of the log file.
   */
  protected static string $logFileName;

  /**
   * The directory where the log file will be created.
   */
  protected static string $logDirectory;

  /**
   * Private constructor to prevent instantiation.
   */
  private function __construct() {
  }

  /**
   * Sets the directory where the log file will be created.
   *
   * @param string $log_directory
   *   The directory where the log file will be created.
   */
  public static function setLogDirectory(string $log_directory): void {
    self::$logDirectory = $log_directory;
  }

  /**
   * Sets the name of the log file.
   *
   * @param string $log_file_name
   *   The name of the log file.
   */
  public static function setLogFileName(string $log_file_name): void {
    self::$logFileName = $log_file_name;
  }

  /**
   * Gets the path to the log file.
   *
   * When a test spawns multiple php processes, e.g. for ajax requests, each of
   * them will have its own unique log file.
   *
   * If the log file name is not set, a unique random name will be generated.
   *
   * @return string
   *   The path to the log file.
   *
   * @throws \RuntimeException
   *   Thrown when the log file directory is not set.
   */
  public static function getLogFile(): string {
    if (!isset(self::$logDirectory)) {
      throw new \RuntimeException('Log directory not set.');
    }
    // If the log file name is not set, generate a unique random name that
    // cannot be re-used by another process.
    if (!isset(self::$logFileName)) {
      do {
        $log_name = mt_rand(10000000, 99999999) . '.log';
        $fd = fopen(static::$logDirectory . '/' . $log_name, 'x');
      } while ($fd === FALSE);

      fwrite($fd, "");
      fclose($fd);

      static::setLogFileName($log_name);
    }
    return self::$logDirectory . '/' . self::$logFileName;
  }

  /**
   * Appends a serialized error to the log file.
   *
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
  public static function writeError(string $message, string $type, string $function, string $file, int $line, ?\Throwable $exception): void {
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
    $log_file = static::getLogFile();
    file_put_contents($log_file, rawurlencode(serialize($assertion)) . "\n", FILE_APPEND);
  }

  /**
   * Reads all logged entries, clears the log, and re-triggers the errors.
   *
   * Deprecations are re-triggered via trigger_error() so they can be collected
   * by PHPUnit. All other error types throw an exception so they can be tested
   * by PHPUnit.
   *
   * @throws \Exception
   *   If a non-deprecation error is found in the log.
   */
  public static function reTriggerErrors(): void {
    if (!isset(static::$logDirectory, static::$logFileName)) {
      throw new \Exception('Log directory or file name not set.');
    }
    $log_file = static::$logDirectory . '/' . static::$logFileName;
    if (!file_exists($log_file)) {
      return;
    }
    $content = file_get_contents($log_file);
    unlink($log_file);
    if (empty($content)) {
      return;
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

}
