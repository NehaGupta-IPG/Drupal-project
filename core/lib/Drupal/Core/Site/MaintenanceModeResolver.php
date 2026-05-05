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
 * Checks both the MAINTENANCE_MODE constant (set by update.php and
 * install.php) and the system.maintenance_mode state value.
 *
 * @see \Drupal\Core\Site\MaintenanceModeInterface
 */
class MaintenanceModeResolver {

  /**
   * Determines whether the system is currently in maintenance mode.
   *
   * This checks for the MAINTENANCE_MODE constant and the
   * system.maintenance_mode state setting. It does NOT check user
   * permissions or route exemptions.
   *
   * @return bool
   *   TRUE if the system is in maintenance mode.
   */
  public function isMaintenance(): bool {
    // Check the constant first — set by update.php, install.php, etc.
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

}
