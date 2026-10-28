<?php

declare(strict_types=1);

namespace Drupal\locale\Install\Installer;

use Drupal\Core\Extension\Install\InstallInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\locale\StreamWrapper\TranslationsStream;

/**
 * Install tasks for Locale module.
 */
class LocaleFieldHooks implements InstallInterface {

  /**
   * Implements hook_install().
   */
  public function doInstall(bool $isSyncing): void {
    // Create the interface translations directory and ensure it's writable.
    $directory = TranslationsStream::basePath();
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }

}
