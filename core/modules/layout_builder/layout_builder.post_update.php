<?php

/**
 * @file
 * Post update functions for Layout Builder.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Implements hook_removed_post_updates().
 */
function layout_builder_removed_post_updates(): array {
  return [
    'layout_builder_post_update_rebuild_plugin_dependencies' => '9.0.0',
    'layout_builder_post_update_add_extra_fields' => '9.0.0',
    'layout_builder_post_update_section_storage_context_definitions' => '9.0.0',
    'layout_builder_post_update_overrides_view_mode_annotation' => '9.0.0',
    'layout_builder_post_update_cancel_link_to_discard_changes_form' => '9.0.0',
    'layout_builder_post_update_remove_layout_is_rebuilding' => '9.0.0',
    'layout_builder_post_update_routing_entity_form' => '9.0.0',
    'layout_builder_post_update_discover_blank_layout_plugin' => '9.0.0',
    'layout_builder_post_update_routing_defaults' => '9.0.0',
    'layout_builder_post_update_discover_new_contextual_links' => '9.0.0',
    'layout_builder_post_update_fix_tempstore_keys' => '9.0.0',
    'layout_builder_post_update_section_third_party_settings_schema' => '9.0.0',
    'layout_builder_post_update_layout_builder_dependency_change' => '9.0.0',
    'layout_builder_post_update_update_permissions' => '9.0.0',
    'layout_builder_post_update_make_layout_untranslatable' => '9.0.0',
    'layout_builder_post_update_override_entity_form_controller' => '10.0.0',
    'layout_builder_post_update_section_storage_context_mapping' => '10.0.0',
    'layout_builder_post_update_tempstore_route_enhancer' => '10.0.0',
    'layout_builder_post_update_timestamp_formatter' => '11.0.0',
    'layout_builder_post_update_enable_expose_field_block_feature_flag' => '11.0.0',
  ];
}

/**
 * Adds the layout translation settings field.
 */
function layout_builder_post_update_add_translation_field() {
  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  $field_map = $field_manager->getFieldMap();
  foreach ($field_map as $entity_type_id => $field_infos) {
    if (isset($field_infos[OverridesSectionStorage::FIELD_NAME]['bundles'])) {
      foreach ($field_infos[OverridesSectionStorage::FIELD_NAME]['bundles'] as $bundle) {
        // The field map can contain stale information. If the field does not
        // exist, ignore it. The field map will be rebuilt when the cache is
        // cleared at the end of the update process.
        if (!FieldConfig::loadByName($entity_type_id, $bundle, OverridesSectionStorage::FIELD_NAME)) {
          continue;
        }
        _layout_builder_add_translation_field($entity_type_id, $bundle);

      }
    }

  }
}

/**
 * Adds a layout translation field to a given bundle.
 *
 * @param string $entity_type_id
 *   The entity type ID.
 * @param string $bundle
 *   The bundle.
 */
function _layout_builder_add_translation_field($entity_type_id, $bundle) {
  $field_name = OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME;
  $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
  if (!$field) {
    $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'entity_type' => $entity_type_id,
        'field_name' => $field_name,
        'type' => 'layout_translation',
        'locked' => TRUE,
      ]);
      $field_storage->setTranslatable(TRUE);
      $field_storage->save();
    }

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => t('Layout Labels'),
    ]);
    $field->save();
  }
}
