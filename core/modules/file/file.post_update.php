<?php

/**
 * @file
 * Post update functions for File.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

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
 * Adds the 'uri_scheme_field' setting to file and image field storages.
 */
function file_post_update_add_uri_scheme_field(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'field_storage_config', function ($field_storage) {
    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
    if (!in_array($field_storage->getType(), ['file', 'image'], TRUE) || array_key_exists('uri_scheme_field', $field_storage->get('settings'))) {
      return FALSE;
    }
    $field_storage->setSetting('uri_scheme_field', FALSE);
    return TRUE;
  });
}
