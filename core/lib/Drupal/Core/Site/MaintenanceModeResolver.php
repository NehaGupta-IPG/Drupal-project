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
   *
   * @var \Drupal\Core\Site\MaintenanceType|string|bool|null
   */
  private static MaintenanceType|string|bool|null $override = NULL;

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
    return (bool) $this->getMaintenanceType();
  }

  /**
   * Returns the current maintenance type.
   *
   * @return \Drupal\Core\Site\MaintenanceType|string|null
   *   The maintenance type.
   */
  public function getMaintenanceType(): MaintenanceType|string|null {
    // Check programmatic override first.
    if (self::$override !== NULL) {
      return $this->toMaintenanceType(self::$override);
    }

    // Check the constant — set by update.php, install.php, etc.
    // @todo Remove this constant check in drupal:13.0.0 once update.php and
    //   install.php use enableMaintenance() instead.
    //   @see https://www.drupal.org/project/drupal/issues/3000229
    if (defined('MAINTENANCE_MODE')) {
      return $this->toMaintenanceType(constant('MAINTENANCE_MODE'));
    }

    // Check the state value set via the admin UI.
    try {
      return \Drupal::state()->get('system.maintenance_mode', FALSE) ? MaintenanceType::Regular : NULL;
    }
    catch (\Exception) {
      // State service may not be available during early boot.
      return NULL;
    }
  }

  /**
   * Checks if the system is in a specific maintenance type.
   *
   * @param \Drupal\Core\Site\MaintenanceType|string $type
   *   The maintenance type to check.
   *
   * @return bool
   *   TRUE if the system is in the specified maintenance type.
   */
  public function is(MaintenanceType|string $type): bool {
    $current = $this->getMaintenanceType();
    if ($current === NULL) {
      return FALSE;
    }

    if ($type instanceof MaintenanceType) {
      return $current === $type;
    }

    return $current instanceof MaintenanceType ? $current->value === $type : $current === $type;
  }

  /**
   * Converts a raw value to a MaintenanceType enum if possible.
   *
   * @param \Drupal\Core\Site\MaintenanceType|string|bool $value
   *   The raw value.
   *
   * @return \Drupal\Core\Site\MaintenanceType|string
   *   The converted value.
   */
  private function toMaintenanceType(MaintenanceType|string|bool $value): MaintenanceType|string {
    if ($value instanceof MaintenanceType) {
      return $value;
    }
    if ($value === TRUE || $value === 'regular') {
      return MaintenanceType::Regular;
    }
    return MaintenanceType::tryFrom((string) $value) ?? (string) $value;
  }

  /**
   * Programmatically enables maintenance mode.
   *
   * This is intended to replace `define('MAINTENANCE_MODE', ...)` in
   * update.php, install.php, and similar entry points. The override
   * takes effect immediately and persists for the lifetime of the
   * current PHP process.
   *
   * @param \Drupal\Core\Site\MaintenanceType|string|bool $type
   *   (optional) The maintenance type. Defaults to TRUE.
   *
   * @return $this
   */
  public function enableMaintenance(MaintenanceType|string|bool $type = TRUE): static {
    self::$override = $type;
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
