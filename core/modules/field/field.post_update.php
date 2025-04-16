<?php

/**
 * @file
 * Post update functions for Field module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\field\FieldConfigInterface;

/**
 * Converts empty `description` on fields to NULL.
 */
function field_post_update_set_field_config_empty_description_to_null(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'field_config', function (FieldConfigInterface $entity): bool {
      if (trim($entity->getDescription()) === '') {
        $entity->set('description', NULL);
        return TRUE;
      }
      return FALSE;
    });
}

/**
 * Converts empty `description` on base field overrides to NULL.
 */
function field_post_update_set_base_field_override_empty_description_to_null(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'base_field_override', function (BaseFieldOverride $entity): bool {
      if (trim($entity->getDescription()) === '') {
        $entity->set('description', NULL);
        return TRUE;
      }
      return FALSE;
    });
}

/**
 * Implements hook_removed_post_updates().
 */
function field_removed_post_updates(): array {
  return [
    'field_post_update_save_custom_storage_property' => '9.0.0',
    'field_post_update_entity_reference_handler_setting' => '9.0.0',
    'field_post_update_email_widget_size_setting' => '9.0.0',
    'field_post_update_remove_handler_submit_setting' => '9.0.0',
  ];
}
