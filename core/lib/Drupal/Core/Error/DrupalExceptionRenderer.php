<?php

declare(strict_types=1);

namespace Drupal\Core\Error;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Utility\Error;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders uncaught exceptions and fatal errors for Drupal.
 *
 * This class serves as Symfony ErrorHandler's exception handler callback
 * (set via setExceptionHandler()). It replaces the procedural
 * _drupal_exception_handler() and the fatal-error branch of
 * _drupal_log_error().
 *
 * It is called for:
 * - Uncaught exceptions that escape the HttpKernel
 * - Fatal errors detected at shutdown by Symfony's ErrorHandler
 *
 * @see \Drupal\Core\Error\DrupalErrorHandler
 */
final class DrupalExceptionRenderer {

  /**
   * Renders an exception as an error response.
   *
   * This is the main entry point, used as the callable for
   * Symfony ErrorHandler's setExceptionHandler().
   *
   * @param \Throwable $exception
   *   The exception or error to render.
   */
  public static function renderException(\Throwable $exception): void {
    try {
      static::doRenderException($exception);
    }
    catch (\Throwable $additionalException) {
      static::renderAdditionalException($exception, $additionalException);
    }
  }

  /**
   * Performs the actual exception rendering.
   *
   * @param \Throwable $exception
   *   The exception to render.
   */
  private static function doRenderException(\Throwable $exception): void {
    $error = Error::decodeException($exception);
    $isInstaller = InstallerKernel::installationAttempted() && \Drupal::hasContainer();

    // Separate non-replaceable values from the error array.
    $backtrace = $error['backtrace'];
    $severity = $error['severity_level'];
    unset($error['backtrace'], $error['exception'], $error['severity_level']);

    // Emit test assertion headers.
    if (defined('DRUPAL_TEST_IN_CHILD_SITE') && DRUPAL_TEST_IN_CHILD_SITE && !headers_sent() && (!defined('SIMPLETEST_COLLECT_ERRORS') || SIMPLETEST_COLLECT_ERRORS)) {
      static::emitTestHeader($error);
    }

    // Log to Drupal's logger if available.
    static::logException($error, $severity, $backtrace, $exception);

    // Always log fatal errors to PHP's error log for developers.
    error_log(sprintf('%s: %s in %s on line %d %s', $error['%type'], $error['@message'], $error['%file'], $error['%line'], $error['@backtrace_string']));

    // Render the response based on the SAPI / request context.
    $response = new Response();

    if (PHP_SAPI === 'cli') {
      $response->setContent(html_entity_decode(strip_tags(new FormattableMarkup(Error::DEFAULT_ERROR_MESSAGE, $error))) . "\n");
      $response->send();
      exit(1);
    }

    if (\Drupal::hasRequest() && \Drupal::request()->isXmlHttpRequest()) {
      if (ErrorDisplayLevel::resolve()->isDisplayable($error)) {
        $response->setContent(new FormattableMarkup(Error::DEFAULT_ERROR_MESSAGE, $error));
        $response->send();
      }
      exit;
    }

    // HTML response.
    $message = '';
    if (ErrorDisplayLevel::resolve()->isDisplayable($error)) {
      $class = 'error';

      // Treat 'User notice' as debug information.
      if ($error['%type'] == 'User notice') {
        $error['%type'] = 'Debug';
        $class = 'status';
      }

      // Reduce verbosity by removing DRUPAL_ROOT from the file path.
      $error = static::simplifyFilePath($error);

      $displayLevel = ErrorDisplayLevel::resolve();
      if (!$displayLevel->isVerbose()) {
        $message = new FormattableMarkup(Error::DEFAULT_ERROR_MESSAGE, $error);
      }
      else {
        // Include backtrace in verbose mode.
        array_shift($backtrace);
        $error['@backtrace'] = Error::formatBacktrace(array_map(function ($trace) {
          unset($trace['args']);
          return $trace;
        }, $backtrace));
        $message = new FormattableMarkup('<details class="error-with-backtrace"><summary>' . Error::DEFAULT_ERROR_MESSAGE . '</summary><pre class="backtrace">@backtrace</pre></details>', $error);
      }
    }

    // Build the fatal error page.
    $content = 'The website encountered an unexpected error. Try again later.<br />' . $message;

    if ($isInstaller) {
      $output = [
        '#title' => 'Error',
        '#markup' => $content,
      ];
      try {
        install_display_output($output, $GLOBALS['install_state']);
        exit;
      }
      catch (\Throwable) {
        // The maintenance page failed, fall back to plain error message.
      }
    }

    $response->setContent($content);
    $response->setStatusCode(500, '500 Service unavailable (with message)');
    $response->send();
    exit;
  }

  /**
   * Handles additional exceptions thrown during exception rendering.
   *
   * @param \Throwable $original
   *   The original exception.
   * @param \Throwable $additional
   *   The additional exception thrown while handling the original.
   */
  private static function renderAdditionalException(\Throwable $original, \Throwable $additional): void {
    if (ErrorDisplayLevel::resolve()->isDisplayable()) {
      print '<h1>Additional uncaught exception thrown while handling exception.</h1>';
      print '<h2>Original</h2><p>' . Error::renderExceptionSafe($original) . '</p>';
      print '<h2>Additional</h2><p>' . Error::renderExceptionSafe($additional) . '</p><hr />';
    }
  }

  /**
   * Logs the exception to Drupal's logger system.
   *
   * @param array $error
   *   The decoded error array.
   * @param int $severity
   *   The RfcLogLevel severity.
   * @param array $backtrace
   *   The PHP backtrace.
   * @param \Throwable $exception
   *   The original exception.
   */
  private static function logException(array $error, int $severity, array $backtrace, \Throwable $exception): void {
    if (!\Drupal::hasService('logger.factory')) {
      return;
    }

    try {
      \Drupal::logger('php')
        ->log($severity, Error::DEFAULT_ERROR_MESSAGE . ' @backtrace_string.', $error + [
          'backtrace' => $backtrace,
          'exception' => $exception,
          'severity_level' => $severity,
        ]);
    }
    catch (\Throwable) {
      error_log(strtr('Failed to log error: ' . Error::DEFAULT_ERROR_MESSAGE . ' @backtrace_string', $error));
    }
  }

  /**
   * Emits a test assertion header for the error.
   *
   * @param array $error
   *   The decoded error array.
   */
  private static function emitTestHeader(array $error): void {
    static $number = 0;
    $assertion = [
      $error['@message'],
      $error['%type'],
      [
        'function' => $error['%function'],
        'file' => $error['%file'],
        'line' => $error['%line'],
      ],
    ];
    header('X-Drupal-Assertion-' . $number . ': ' . rawurlencode(serialize($assertion)));
    $number++;
  }

  /**
   * Attempts to reduce file path verbosity in the error.
   *
   * Removes DRUPAL_ROOT from the file path to prevent full path disclosure.
   *
   * @param array $error
   *   The error array.
   *
   * @return array
   *   The error array with simplified file path.
   */
  private static function simplifyFilePath(array $error): array {
    try {
      $root = \Drupal::root();
    }
    catch (\Throwable) {
      $root = realpath(dirname(__DIR__, 4));
    }
    if (str_starts_with($error['%file'], $root)) {
      $error['%file'] = substr($error['%file'], strlen($root) + 1);
    }
    return $error;
  }

}
