<?php

/**
 * @file
 * Post update functions for File.
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\field\FieldConfigInterface;

/**
 * Implements hook_removed_post_updates().
 */
function file_removed_post_updates(): array {
  return [
    'file_post_update_add_txt_if_allows_insecure_extensions' => '10.0.0',
    'file_post_update_add_permissions_to_roles' => '11.0.0',
    'file_post_update_add_default_filename_sanitization_configuration' => '11.0.0',
    'file_post_update_add_playsinline' => '12.0.0',
  ];
}

/**
 * Adds a value for the 'playsinline' setting of the 'file_video' formatter.
 *
 * @deprecated in drupal:12.3.0 and is removed from drupal:13.0.0. This was
 *   rolled back due to a bug in the implementation and is now a no-op function
 *   and is kept only to prevent errors when updating.
 *
 * @see https://www.drupal.org/project/drupal/issues/3533291
 */
function file_post_update_add_playsinline(array &$sandbox = []): ?TranslatableMarkup {
  // No-op.
  return NULL;
}

/**
 * Add the 'require description' file field setting.
 */
function file_post_update_description_required(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'field_config', function (FieldConfigInterface $field_config): bool {
    if ($field_config->getType() !== 'file') {
      return FALSE;
    }
    $settings = $field_config->getSettings();
    if (array_key_exists('description_field_required', $settings)) {
      return FALSE;
    }
    $settings['description_field_required'] = FALSE;
    $field_config->setSettings($settings);
    return TRUE;
  });
}
