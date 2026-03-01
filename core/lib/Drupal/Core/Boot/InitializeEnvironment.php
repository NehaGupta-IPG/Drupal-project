<?php

namespace Drupal\Core\Boot;

use Drupal\Core\Test\TestDatabase;

/**
 * Initialize the environment.
 */
class InitializeEnvironment {

  public function __construct(protected ApplicationRootGuesser $applicationRootGuesser) {
  }

  /**
   * Whether the PHP environment has been initialized.
   *
   * This legacy phase can only be booted once because it sets session INI
   * settings. If a session has already been started, re-generating these
   * settings would break the session.
   *
   * @var bool
   */
  protected static bool $isEnvironmentInitialized = FALSE;

  /**
   * Setup a consistent PHP environment.
   *
   * This method sets PHP environment options we want to be sure are set
   * correctly for security or just saneness.
   *
   * @param string $app_root
   *   (optional) The path to the application root as a string. If not supplied,
   *   the application root will be computed.
   */
  public function __invoke($app_root = NULL): void {
    if (static::$isEnvironmentInitialized) {
      return;
    }
    // Determine the application root if it's not supplied.
    if ($app_root === NULL) {
      $app_root = ($this->applicationRootGuesser)();
    }

    error_reporting(E_ALL);

    // Override PHP settings required for Drupal to work properly.
    // sites/default/default.settings.php contains more runtime settings.
    // The .htaccess file contains settings that cannot be changed at runtime.

    if (PHP_SAPI !== 'cli') {
      // Use session cookies, not transparent sessions that puts the session id
      // in the query string.
      ini_set('session.use_cookies', '1');
      ini_set('session.use_strict_mode', '1');
      // Don't send HTTP headers using PHP's session handler.
      // Send an empty string to disable the cache limiter.
      ini_set('session.cache_limiter', '');
      // Use httponly session cookies.
      ini_set('session.cookie_httponly', '1');
    }

    // Set sane locale settings, to ensure consistent string, dates, times and
    // numbers handling.
    setlocale(LC_ALL, 'C.UTF-8', 'C');

    // Set appropriate configuration for multi-byte strings.
    mb_internal_encoding('utf-8');
    mb_language('uni');

    // Indicate that code is operating in a test child site.
    if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
      if ($test_prefix = drupal_valid_test_ua()) {
        $test_db = new TestDatabase($test_prefix);
        // Only code that interfaces directly with tests should rely on this
        // constant; e.g., the error/exception handler conditionally adds
        // further error information into HTTP response headers that are
        // consumed by the internal browser.
        define('DRUPAL_TEST_IN_CHILD_SITE', TRUE);

        // Log fatal errors to the test site directory.
        ini_set('log_errors', 1);
        ini_set('error_log', $app_root . '/' . $test_db->getTestSitePath() . '/error.log');

        // Ensure that a rewritten settings.php is used if OPcache is on.
        ini_set('opcache.validate_timestamps', 'on');
        ini_set('opcache.revalidate_freq', 0);
      }
      else {
        // Ensure that no other code defines this.
        define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
      }
    }

    // Set the Drupal custom error handler.
    set_error_handler('_drupal_error_handler');
    set_exception_handler('_drupal_exception_handler');
    static::$isEnvironmentInitialized = TRUE;
  }

}
