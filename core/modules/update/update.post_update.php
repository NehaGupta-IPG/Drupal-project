<?php

/**
 * @file
 * Post update functions for Update Status.
 */

use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\update\DataSharing;

/**
 * Implements hook_removed_post_updates().
 */
function update_remove_post_updates() {
  return [
    'update_post_update_add_view_update_notifications_permission' => '10.0.0',
    'update_post_update_set_blank_fetch_url_to_null' => '11.0.0',
    'update_post_update_clear_disk_cache' => '12.0.0',
    'update_post_update_fix_update_emails' => '12.0.0',
  ];
}

/**
 * Removes the legacy 'Update Manager' disk cache (again).
 */
function update_post_update_clear_disk_cache_again(): void {
  // @see _update_manager_unique_id()
  $id = substr(hash('sha256', Settings::getHashSalt()), 0, 8);
  // List of legacy 'Update Manager' cache directories.
  $directories = [
    // @see _update_manager_cache_directory()
    "temporary://update-cache-$id",
    // @see _update_manager_extract_directory()
    "temporary://update-extraction-$id",
  ];
  foreach ($directories as $directory) {
    if (is_dir($directory)) {
      \Drupal::service('file_system')->deleteRecursive($directory);
    }
  }
}

/**
 * Adds the data_sharing settings to update.settings.
 */
function update_post_update_add_data_sharing_settings(): ?TranslatableMarkup {
  $config = \Drupal::configFactory()->getEditable('update.settings');
  $data_sharing = ($config->get('data_sharing') ?? []) + [
    'domain' => NULL,
    'site_mail' => NULL,
  ];
  $config->set('data_sharing', $data_sharing)->save();
  if ($data_sharing['domain'] === NULL || $data_sharing['site_mail'] === NULL) {
    $start = \Drupal::time()->getRequestTime();
    \Drupal::state()->set(DataSharing::NOTICE_START_STATE_KEY, $start);
    return t('Choose what this site shares with Drupal.org at @url before @date, when the recommended settings apply.', [
      '@url' => Url::fromRoute('update.settings')->toString(),
      '@date' => \Drupal::service('date.formatter')->format($start + DataSharing::NOTICE_PERIOD, 'custom', 'Y-M-d'),
    ]);
  }
  return NULL;
}
