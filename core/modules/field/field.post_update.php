<?php

/**
 * @file
 * Post update functions for Field module.
 */

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldConfig;

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

/**
 * Removes the purge_batch_size config.
 */
function field_post_update_clear_purge_batch_size(): ?TranslatableMarkup {
  $config = \Drupal::configFactory()->getEditable('field.settings');
  if (!$config->isNew()) {
    $purge_batch_size = $config->get('purge_batch_size');
    if ($purge_batch_size !== Settings::get('field_purge_batch_size', 50)) {
      return t("The configuration field.settings:field_purge_batch_size is deprecated and is set to non-default value @field_purge_batch_size. Support for setting this through configuration will be removed in Drupal 13.0.0 and must set in settings.php: \$settings['field_purge_batch_size'] = '@field_purge_batch_size';, see https://www.drupal.org/node/3494023.", [
        '@field_purge_batch_size' => $purge_batch_size,
      ]);
    }
    else {
      // Remove the configuration as purge_batch_size is the only key.
      $config->delete();
    }
  }
  return NULL;
}

/**
 * Update the default plugins for entity reference selection handler plugins.
 */
function field_post_update_add_entity_reference_selection_plugin_option(): void {
  foreach (FieldConfig::loadMultiple() as $field_config) {
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $class = $field_type_manager->getPluginClass($field_config->getType());
    if ($class === EntityReferenceItem::class || is_subclass_of($class, EntityReferenceItem::class)) {
      $settings = $field_config->getSettings();
      $settings['handler_settings']['include_unpublished_entities'] = FALSE;
      $field_config->setSettings($settings);
      $field_config->save();
    }
  }
}
