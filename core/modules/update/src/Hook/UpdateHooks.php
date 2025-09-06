<?php

namespace Drupal\update\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the update module.
 */
class UpdateHooks {

  use StringTranslationTrait;

  /**
   * Install and uninstall hook implementations.
   *
   * Implements hook_themes_installed().
   * Implements hook_themes_uninstalled().
   * Implements hook_modules_installed().
   * Implements hook_modules_uninstalled().
   *
   * If themes or modules are installed or uninstalled, we invalidate the
   * information of available updates.
   */
  #[Hook('themes_installed')]
  #[Hook('themes_uninstalled')]
  #[Hook('modules_installed')]
  #[Hook('modules_uninstalled')]
  public function clearUpdateStorage($extensions): void {
    // Clear all Update Status module data.
    update_storage_clear();
  }

}
