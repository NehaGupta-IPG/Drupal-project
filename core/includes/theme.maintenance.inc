<?php

/**
 * @file
 * Theming for maintenance pages.
 */

use Drupal\Core\Site\MaintenanceModeInterface;

/**
 * Sets up the theming system for maintenance page.
 *
 * Used for site installs, updates and when the site is in maintenance mode.
 * It also applies when the database is unavailable or bootstrap was not
 * complete. The admin theme is always used for the initial install and update
 * operations, but this can be overridden by setting a "maintenance_theme" key
 * in the $settings variable in settings.php.
 *
 * @deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use
 *   \Drupal::service('maintenance_mode')->setTheme() instead.
 *
 * @see https://www.drupal.org/node/3058979
 * @see \Drupal\Core\Installer\InstallerKernel::INSTALL_THEME
 */
function _drupal_maintenance_theme(): void {
  @trigger_error(__FUNCTION__ . "() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use \Drupal::service('maintenance_mode')->setTheme() instead. See https://www.drupal.org/node/3058979", E_USER_DEPRECATED);
  \Drupal::service(MaintenanceModeInterface::class)->setTheme();
}
