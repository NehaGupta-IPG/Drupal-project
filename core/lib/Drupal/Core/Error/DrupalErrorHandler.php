<?php

declare(strict_types=1);

namespace Drupal\Core\Error;

use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * Drupal's error handler wrapping Symfony's ErrorHandler component.
 *
 * This class uses composition to wrap Symfony's @final ErrorHandler and
 * configures it for Drupal's specific needs:
 * - BufferingLogger for early-boot error capture (before the container)
 * - DrupalExceptionRenderer as the exception handler callback
 * - DrupalErrorLogger as the PSR-3 bridge to Drupal's watchdog
 * - throwAt(0, true) to prevent error-to-exception conversion (BC)
 *
 * Usage:
 * @code
 *   // In DrupalKernel::bootEnvironment():
 *   DrupalErrorHandler::register();
 *
 *   // In DrupalKernel::preHandle(), after container is available:
 *   DrupalErrorHandler::getInstance()->upgradeLogger(
 *     \Drupal::service('logger.factory')->get('php')
 *   );
 * @endcode
 *
 * @see \Symfony\Component\ErrorHandler\ErrorHandler
 * @see \Drupal\Core\Error\DrupalExceptionRenderer
 * @see \Drupal\Core\Error\DrupalErrorLogger
 */
final class DrupalErrorHandler {

  /**
   * The singleton instance.
   *
   * @var self|null
   */
  private static ?self $instance = NULL;

  /**
   * The Symfony ErrorHandler instance.
   */
  private ErrorHandler $symfonyHandler;

  /**
   * The Drupal error logger bridge.
   */
  private DrupalErrorLogger $drupalErrorLogger;

  /**
   * The buffering logger used during early boot.
   */
  private ?BufferingLogger $bufferingLogger = NULL;

  /**
   * Whether Symfony error enhancers are enabled.
   */
  private bool $errorEnhancersEnabled = FALSE;

  /**
   * Constructs a DrupalErrorHandler.
   *
   * Use DrupalErrorHandler::register() instead of constructing directly.
   */
  private function __construct() {
    $this->drupalErrorLogger = new DrupalErrorLogger();
    $this->symfonyHandler = ErrorHandler::register(NULL, TRUE);
    $this->symfonyHandler->setDefaultLogger($this->drupalErrorLogger);

    // Configure Drupal's exception renderer as the exception handler.
    $this->symfonyHandler->setExceptionHandler(
      [DrupalExceptionRenderer::class, 'renderException'],
    );

    // Prevent Symfony from converting PHP errors to \ErrorException.
    // Drupal's legacy behavior is to log errors, not throw them.
    // Note: Symfony always forces E_RECOVERABLE_ERROR | E_USER_ERROR to be
    // thrown regardless of this setting. This matches Drupal's treatment of
    // recoverable errors as fatal.
    $this->symfonyHandler->throwAt(0, TRUE);

    // Disable scoped errors (local variable preservation) for performance.
    $this->symfonyHandler->scopeAt(0, TRUE);
  }

  /**
   * Registers the Drupal error handler.
   *
   * This is the main entry point. It creates the singleton instance and
   * registers the Symfony ErrorHandler with Drupal-specific configuration.
   *
   * @return self
   *   The registered handler instance.
   */
  public static function register(): self {
    if (self::$instance === NULL) {
      self::$instance = new self();
    }

    // Check if a Symfony ErrorHandler is already registered.
    $current = get_error_handler();
    $isRegistered = is_array($current) && $current[0] instanceof ErrorHandler;

    if (!$isRegistered && !defined('PHPUNIT_COMPOSER_INSTALL') && !defined('__PHPUNIT_PHAR__')) {
      ErrorHandler::register(self::$instance->symfonyHandler);
    }

    return self::$instance;
  }

  /**
   * Returns the singleton instance, or NULL if not yet registered.
   *
   * @return self|null
   *   The handler instance, or NULL.
   */
  public static function getInstance(): ?self {
    return self::$instance;
  }

  /**
   * Upgrades the logger once the container is available.
   *
   * Replaces the BufferingLogger with the real Drupal logger and replays
   * any errors that were captured during early boot.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The Drupal logger channel, typically from logger.factory->get('php').
   */
  public function upgradeLogger(LoggerInterface $logger): void {
    // Upgrade the DrupalErrorLogger with the real Drupal logger.
    $this->drupalErrorLogger->setDrupalLogger($logger);

    // Replace the buffering logger with the Drupal error logger on the
    // Symfony handler. This automatically replays buffered early-boot errors.
    $this->symfonyHandler->setDefaultLogger($this->drupalErrorLogger, NULL, TRUE);

    // Clear the buffering logger reference.
    $this->bufferingLogger = NULL;

    // Apply configuration from the container now that it's available.
    $this->applyConfiguration();
  }

  /**
   * Applies logging configuration from system.logging.
   *
   * Reads the `error_enhancers` and `log_silenced` settings and configures
   * the handler accordingly.
   */
  private function applyConfiguration(): void {
    try {
      $config = \Drupal::config('system.logging');

      // Store error_enhancers flag. When TRUE, Symfony's error enhancers
      // (ClassNotFoundErrorEnhancer, UndefinedFunctionErrorEnhancer,
      // UndefinedMethodErrorEnhancer) will enhance exception messages.
      // This is controlled via ErrorHandler::enhanceError() which is called
      // by FinalExceptionSubscriber when this flag is enabled.
      $this->errorEnhancersEnabled = (bool) $config->get('error_enhancers');

      // When log_silenced is enabled, configure Symfony to log errors that
      // are suppressed with the @-operator. This uses Symfony's "scream"
      // feature which ignores the @-operator for logging purposes.
      if ($config->get('log_silenced')) {
        $this->symfonyHandler->screamAt(E_ALL, TRUE);
      }
    }
    catch (\Exception) {
      // Config may not be available (e.g. during install). Silently skip.
    }
  }

  /**
   * Returns the underlying Symfony ErrorHandler instance.
   *
   * This allows advanced configuration such as:
   * - $handler->getSymfonyHandler()->throwAt(E_RECOVERABLE_ERROR) for opt-in
   * - $handler->getSymfonyHandler()->screamAt(E_ALL) to log silenced errors
   *
   * @return \Symfony\Component\ErrorHandler\ErrorHandler
   *   The Symfony ErrorHandler instance.
   */
  public function getSymfonyHandler(): ErrorHandler {
    return $this->symfonyHandler;
  }

  /**
   * Returns the Drupal error logger bridge.
   *
   * @return \Drupal\Core\Error\DrupalErrorLogger
   *   The Drupal error logger.
   */
  public function getDrupalErrorLogger(): DrupalErrorLogger {
    return $this->drupalErrorLogger;
  }

  /**
   * Returns whether error enhancers are enabled.
   *
   * @return bool
   *   TRUE if error enhancers are enabled.
   */
  public function isErrorEnhancersEnabled(): bool {
    return $this->errorEnhancersEnabled;
  }

  /**
   * Resets the singleton instance.
   *
   * @internal
   *   This method is only intended for use in tests.
   */
  public static function reset(): void {
    self::$instance = NULL;
  }

}
