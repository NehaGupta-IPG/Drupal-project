<?php

declare(strict_types=1);

namespace Drupal\Core\Error;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Utility\Error;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * PSR-3 logger bridge for Drupal's error handling system.
 *
 * Routes error logs from Symfony's ErrorHandler to Drupal's logger system.
 * Handles three responsibilities:
 * - Logging errors to Drupal's watchdog via logger.factory
 * - Emitting X-Drupal-Assertion-* HTTP headers for the test framework
 * - Adding displayable error messages to Drupal's messenger service
 *
 * This logger is set as the default logger on Symfony's ErrorHandler and
 * replaces the logging portions of _drupal_error_handler_real() and
 * _drupal_log_error().
 *
 * @see \Drupal\Core\Error\DrupalErrorHandler
 */
final class DrupalErrorLogger extends AbstractLogger {

  /**
   * Counter for test assertion headers.
   *
   * Each error emitted during a test request gets a unique header number.
   */
  private int $assertionHeaderNumber = 0;

  /**
   * Constructs a DrupalErrorLogger.
   *
   * @param \Psr\Log\LoggerInterface|null $drupalLogger
   *   The Drupal logger channel for 'php' errors. NULL when the container
   *   is not yet available (early boot).
   */
  public function __construct(
    private ?LoggerInterface $drupalLogger = NULL,
  ) {}

  /**
   * Upgrades the logger once the container is available.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The Drupal logger channel for 'php' errors.
   */
  public function setDrupalLogger(LoggerInterface $logger): void {
    $this->drupalLogger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    // Build the Drupal error array from Symfony's error context.
    $error = $this->buildErrorArray($message, $context);
    $severity = $context['severity_level'] ?? ErrorLevels::getSeverity($context['type'] ?? \E_ERROR)['rfcLevel'];

    // Emit test assertion headers when running inside the test framework.
    $this->emitTestHeaders($error);

    // Log to Drupal's logger system if available.
    $this->logToDrupal($error, $severity);

    // Display the error to the user if configured to do so.
    $this->displayToUser($error);
  }

  /**
   * Builds a Drupal error array from a log message and context.
   *
   * @param string|\Stringable $message
   *   The log message.
   * @param array $context
   *   The log context from Symfony ErrorHandler.
   *
   * @return array
   *   Error array with keys: %type, @message, %function, %file, %line,
   *   @backtrace_string.
   */
  private function buildErrorArray(string|\Stringable $message, array $context): array {
    // Symfony ErrorHandler provides structured context. Map it to Drupal's
    // error array format.
    if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
      $error = Error::decodeException($context['exception']);
      if ($context['exception'] instanceof \ErrorException) {
        $error['%type'] = ErrorLevels::getSeverity($context['exception']->getSeverity())['label'];
      }
      return $error;
    }

    $type = $context['type'] ?? \E_ERROR;
    $severity_info = ErrorLevels::getSeverity($type);

    return [
      '%type' => $severity_info['label'],
      '@message' => (string) $message,
      '%function' => $context['function'] ?? 'unknown',
      '%file' => $context['file'] ?? 'unknown',
      '%line' => $context['line'] ?? 0,
      '@backtrace_string' => $context['backtrace_string'] ?? '',
    ];
  }

  /**
   * Emits X-Drupal-Assertion-* headers for the test framework.
   *
   * When running inside a test child site, errors are relayed to the parent
   * test runner via HTTP headers.
   *
   * @param array $error
   *   The Drupal error array.
   */
  public function emitTestHeaders(array $error): void {
    if (!defined('DRUPAL_TEST_IN_CHILD_SITE') || !DRUPAL_TEST_IN_CHILD_SITE) {
      return;
    }
    if (headers_sent()) {
      return;
    }
    if (defined('SIMPLETEST_COLLECT_ERRORS') && !SIMPLETEST_COLLECT_ERRORS) {
      return;
    }

    $assertion = [
      $error['@message'],
      $error['%type'],
      [
        'function' => $error['%function'],
        'file' => $error['%file'],
        'line' => $error['%line'],
      ],
    ];

    header('X-Drupal-Assertion-' . $this->assertionHeaderNumber . ': ' . rawurlencode(serialize($assertion)));
    $this->assertionHeaderNumber++;
  }

  /**
   * Logs an error to Drupal's logger system.
   *
   * @param array $error
   *   The Drupal error array.
   * @param int $severity
   *   The RfcLogLevel severity.
   */
  private function logToDrupal(array $error, int $severity): void {
    if ($this->drupalLogger === NULL) {
      return;
    }

    try {
      $this->drupalLogger->log(
        $severity,
        Error::DEFAULT_ERROR_MESSAGE . ' @backtrace_string',
        $error + ['severity_level' => $severity],
      );
    }
    catch (\Throwable) {
      // We can't log, for example because the database connection is not
      // available. At least try to log to PHP error log.
      error_log(strtr('Failed to log error: ' . Error::DEFAULT_ERROR_MESSAGE . ' @backtrace_string', $error));
    }
  }

  /**
   * Displays an error to the user via Drupal's messenger service.
   *
   * @param array $error
   *   The Drupal error array.
   */
  private function displayToUser(array $error): void {
    // Only display errors if the container and messenger are available.
    if (!\Drupal::hasContainer() || !\Drupal::hasService('messenger')) {
      return;
    }

    $displayLevel = ErrorDisplayLevel::resolve();
    if (!$displayLevel->isDisplayable($error)) {
      return;
    }

    // Map error type to messenger type.
    $class = 'error';
    if ($error['%type'] === 'User notice' || $error['%type'] === 'Debug') {
      $error['%type'] = 'Debug';
      $class = 'status';
    }

    // Build the message.
    if (!$displayLevel->isVerbose()) {
      $message = new FormattableMarkup(Error::DEFAULT_ERROR_MESSAGE, $error);
    }
    else {
      // With verbose logging, include a backtrace.
      $backtrace = $error['backtrace'] ?? [];
      // First trace is the error itself, already in the message.
      if ($backtrace) {
        array_shift($backtrace);
      }
      $error['@backtrace'] = Error::formatBacktrace(array_map(function ($trace) {
        unset($trace['args']);
        return $trace;
      }, $backtrace));
      $message = new FormattableMarkup('<details class="error-with-backtrace"><summary>' . Error::DEFAULT_ERROR_MESSAGE . '</summary><pre class="backtrace">@backtrace</pre></details>', $error);
    }

    try {
      \Drupal::messenger()->addMessage($message, $class, TRUE);
    }
    catch (\Throwable) {
      // If messenger fails, don't crash.
    }
  }

}
