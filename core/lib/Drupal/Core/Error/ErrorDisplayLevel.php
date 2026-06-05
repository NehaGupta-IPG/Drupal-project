<?php

declare(strict_types=1);

namespace Drupal\Core\Error;

use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Site\MaintenanceModeResolver;

/**
 * Error display level configuration.
 *
 * Determines how much error information should be displayed to users.
 * This enum replaces the ERROR_REPORTING_* constants from bootstrap.inc
 * and the procedural error_displayable() and _drupal_get_error_level()
 * functions from errors.inc.
 *
 * @see \Drupal\Core\EventSubscriber\FinalExceptionSubscriber
 */
enum ErrorDisplayLevel: string {

  // Display no errors.
  case Hide = 'hide';

  // Display errors and warnings only.
  case Some = 'some';

  // Display all messages.
  case All = 'all';

  // Display all messages, plus backtrace information.
  case Verbose = 'verbose';

  /**
   * Resolves the current error display level from available configuration.
   *
   * Checks the following sources in order:
   * 1. Installer context: returns Verbose to help users file bug reports.
   * 2. Config factory: reads 'system.logging' config from the database.
   * 3. Settings.php fallback: reads $config['system.logging']['error_level'].
   * 4. Default: returns All if no configuration is available.
   *
   * @return self
   *   The resolved error display level.
   */
  public static function resolve(): self {
    // Raise the error level to maximum for the installer, so users are able to
    // file proper bug reports for installer errors. The returned value is
    // different to the one below, because the installer actually has a
    // 'config.factory' service, which reads the default 'error_level' value
    // from System module's default configuration and the default value is not
    // verbose.
    if (InstallerKernel::installationAttempted()) {
      return self::Verbose;
    }

    $errorLevel = NULL;
    // Try to get the error level configuration from database. If this fails,
    // for example if the database connection is not there, try to read it from
    // settings.php.
    try {
      $errorLevel = \Drupal::config('system.logging')->get('error_level');
    }
    catch (\Exception) {
      $errorLevel = $GLOBALS['config']['system.logging']['error_level'] ?? self::Hide->value;
    }

    // If there is no container or if it has no config.factory service, we are
    // possibly in an edge-case error situation while trying to serve a regular
    // request on a public site, so use the non-verbose default value.
    if (empty($errorLevel)) {
      return self::All;
    }

    return self::tryFrom($errorLevel) ?? self::All;
  }

  /**
   * Determines whether an error should be displayed.
   *
   * When in maintenance mode or when the error level is All or Verbose, all
   * errors should be displayed. For Some, the error will be examined to
   * determine if it should be displayed.
   *
   * @param array|null $error
   *   Optional error to examine for the Some level. If provided, must contain
   *   a '%type' key.
   *
   * @return bool
   *   TRUE if an error should be displayed.
   */
  public function isDisplayable(?array $error = NULL): bool {
    // In maintenance mode, always display errors.
    try {
      $inMaintenance = \Drupal::service(MaintenanceModeResolver::class)->isMaintenance();
    }
    catch (\Exception) {
      // @todo Remove this constant fallback in drupal:13.0.0 once update.php
      //   and install.php no longer rely on defining MAINTENANCE_MODE, and the
      //   MaintenanceModeResolver service is always available.
      //   @see https://www.drupal.org/project/drupal/issues/3000229
      $inMaintenance = defined('MAINTENANCE_MODE');
    }
    if ($inMaintenance) {
      return TRUE;
    }

    return match ($this) {
      self::All, self::Verbose => TRUE,
      self::Some => isset($error) && $error['%type'] !== 'Notice' && $error['%type'] !== 'Strict warning',
      self::Hide => FALSE,
    };
  }

  /**
   * Checks whether verbose error reporting is enabled.
   *
   * @return bool
   *   TRUE when verbose reporting is enabled, FALSE otherwise.
   */
  public function isVerbose(): bool {
    return $this === self::Verbose;
  }

}
