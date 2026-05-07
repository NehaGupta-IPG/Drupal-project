<?php

declare(strict_types=1);

namespace Drupal\Core\Site;

/**
 * Resolves whether the system is in a maintenance-like state.
 *
 * Unlike MaintenanceModeInterface (which requires a route match and is only
 * available after the container is fully booted), this class provides a
 * simple boolean check that works in early-boot and error-handling contexts.
 *
 * Maintenance mode can be activated from three sources (checked in order):
 * 1. Programmatic override via enableMaintenance().
 * 2. The MAINTENANCE_MODE constant (legacy, set by update.php/install.php).
 * 3. The system.maintenance_mode state value (set via the admin UI).
 *
 * @see \Drupal\Core\Site\MaintenanceModeInterface
 */
class MaintenanceModeResolver {

  /**
   * Programmatic maintenance mode override.
   *
   * Static so the override persists across all instances within the same
   * PHP process (e.g. container rebuilds). This mirrors the process-global
   * behavior of define('MAINTENANCE_MODE', ...).
   */
  private static ?bool $override = NULL;

  /**
   * Determines whether the system is currently in maintenance mode.
   *
   * This checks the programmatic override, the MAINTENANCE_MODE constant,
   * and the system.maintenance_mode state setting. It does NOT check user
   * permissions or route exemptions.
   *
   * @return bool
   *   TRUE if the system is in maintenance mode.
   */
  public function isMaintenance(): bool {
    // Check programmatic override first.
    if (self::$override !== NULL) {
      return self::$override;
    }

    // Check the constant — set by update.php, install.php, etc.
    // @todo Remove this constant check in drupal:13.0.0 once update.php and
    //   install.php use enableMaintenance() instead.
    //   @see https://www.drupal.org/project/drupal/issues/3000229
    if (defined('MAINTENANCE_MODE')) {
      return TRUE;
    }

    // Check the state value set via the admin UI.
    try {
      return (bool) \Drupal::state()->get('system.maintenance_mode', FALSE);
    }
    catch (\Exception) {
      // State service may not be available during early boot.
      return FALSE;
    }
  }

  /**
   * Programmatically enables maintenance mode.
   *
   * This is intended to replace `define('MAINTENANCE_MODE', ...)` in
   * update.php, install.php, and similar entry points. The override
   * takes effect immediately and persists for the lifetime of the
   * current PHP process.
   *
   * @return $this
   */
  public function enableMaintenance(): static {
    self::$override = TRUE;
    return $this;
  }

  /**
   * Programmatically disables the maintenance mode override.
   *
   * This clears any override set by enableMaintenance(). The resolver
   * will then fall back to the constant and state checks.
   *
   * @return $this
   */
  public function disableMaintenance(): static {
    self::$override = NULL;
    return $this;
  }

}
