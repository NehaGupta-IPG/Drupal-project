<?php

/**
 * @file
 * Post update functions for File.
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;

/**
 * Implements hook_removed_post_updates().
 */
function file_removed_post_updates(): array {
  return [
    'file_post_update_add_txt_if_allows_insecure_extensions' => '10.0.0',
    'file_post_update_add_permissions_to_roles' => '11.0.0',
    'file_post_update_add_default_filename_sanitization_configuration' => '11.0.0',
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
 * Set the default value for "absolute_url" field formatter setting.
 */
function file_post_update_set_default_absolute_url(array &$sandbox = []): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'entity_view_display', function (EntityViewDisplay $display): bool {
      $fields_settings = $display->get('content');
      $changed = FALSE;
      foreach ($fields_settings as $field_name => $settings) {
        if (!empty($settings['type'])) {
          switch ($settings['type']) {
            case 'file_url_plain':
            case 'image_url':
              $fields_settings[$field_name]['settings']['show_link_as'] = FileFormatterBase::RELATIVE_URL;
              $changed = TRUE;
              break;
          }
        }
      }
      if ($changed === TRUE) {
        $display->set('content', $fields_settings)->save();
        return TRUE;
      }
      return FALSE;
    });
}
