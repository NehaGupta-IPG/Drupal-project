<?php

declare(strict_types=1);

namespace Drupal\locale\Install\Uninstaller;

use Drupal\Core\Extension\Install\UninstallInterface;
use Drupal\Core\File\Exception\FileException;

/**
 * Uninstall tasks for Locale module.
 */
class LocaleFieldHooks implements UninstallInterface {

  /**
   * Implements hook_uninstall().
   */
  public function doUninstall(bool $isSyncing): void {
    $config = \Drupal::config('locale.settings');
    // Delete all JavaScript translation files.
    $locale_js_directory = 'assets://' . $config->get('javascript.directory');

    if (is_dir($locale_js_directory)) {
      $locale_javascripts = \Drupal::state()->get('locale.translation.javascript', []);
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      foreach ($locale_javascripts as $langcode => $file_suffix) {
        if (!empty($file_suffix)) {
          try {
            $file_system->delete($locale_js_directory . '/' . $langcode . '_' . $file_suffix . '.js');
          }
          catch (FileException) {
            // Ignore and continue.
          }
        }
      }
      // Delete the JavaScript translations directory if empty.
      if (is_dir($locale_js_directory)) {
        if (!$file_system->scanDirectory($locale_js_directory, '/.*/')) {
          $file_system->rmdir($locale_js_directory);
        }
      }
    }

    // Clear variables.
    \Drupal::state()->delete('system.javascript_parsed');
    \Drupal::state()->delete('locale.translation.plurals');
    \Drupal::state()->delete('locale.translation.javascript');
  }

}
