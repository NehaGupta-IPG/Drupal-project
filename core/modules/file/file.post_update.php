<?php

/**
 * @file
 * Post update functions for File.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
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
