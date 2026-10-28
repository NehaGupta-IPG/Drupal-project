<?php

declare(strict_types=1);

namespace Drupal\user\Install\Installer;

use Drupal\Core\Extension\Install\InstallInterface;

/**
 * Install tasks for User module.
 */
class UserFieldHooks implements InstallInterface {

  /**
   * Implements hook_install().
   */
  public function doInstall(bool $isSyncing): void {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    // Insert a row for the anonymous user.
    $storage
      ->create([
        'uid' => 0,
        'status' => 0,
        'name' => '',
      ])
      ->save();

    // We need some placeholders here as name and mail are unique.
    // This will be changed by the settings form in the installer.
    $storage
      ->create([
        'uid' => 1,
        'name' => 'placeholder-for-uid-1',
        'mail' => 'placeholder-for-uid-1',
        'status' => TRUE,
      ])
      ->save();
  }

}
