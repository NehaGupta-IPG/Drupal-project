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
function layout_builder_post_update_add_translation_field(): void {
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
function _layout_builder_add_translation_field($entity_type_id, $bundle): void {
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

/**
 * Adds the layout translation settings field.
 */
function layout_builder_post_update_update_block_revisions(array &$sandbox) {
  if (!isset($sandbox['progress'])) {
    // Count all nodes with layout translations.
    $count = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME, NULL, 'IS NOT NULL')
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $sandbox['total_count'] = $count;
    $sandbox['updated_count'] = 0;
    $sandbox['progress'] = 0;

    if (empty($sandbox['total_count'])) {
      $sandbox['#finished'] = 1;
      return;
    }
  }

  $batch_size = 25;
  $nids = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->getQuery()
    ->condition(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME, NULL, 'IS NOT NULL')
    ->accessCheck(FALSE)
    ->range($sandbox['progress'], $batch_size)
    ->execute();
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $nodes = $node_storage->loadMultiple($nids);

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();

    // Loop through the sections and update the referenced block revision id.
    foreach ($sections as $section_array) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = $section_array['section'];
      foreach ($section->getComponents() as $component) {
        $plugin_configuration = $component->getPlugin()->getConfiguration();
        if (!isset($plugin_configuration['block_revision_id'])) {
          continue;
        }
        $block = \Drupal::entityTypeManager()->getStorage('block_content')->loadRevision($plugin_configuration['block_revision_id']);
        if (!$block) {
          continue;
        }
        // Load the default revision of the block.
        $default_revision = \Drupal::entityTypeManager()->getStorage('block_content')->load($block->id());

        // Reference the default revision.
        $plugin_configuration['block_revision_id'] = $default_revision->getRevisionId();
        $component->setConfiguration($plugin_configuration);
      }
    }

    $node->set(OverridesSectionStorage::FIELD_NAME, $sections);
    // Saving the node will trigger InlineBlockEntityOperations::setBlockRevisionForAllTranslations(),
    // causing all translations to be updated as well.
    $node->save();
    $sandbox['updated_count']++;

    $sandbox['progress']++;
  }
  if ($sandbox['progress'] >= $sandbox['total_count']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['total_count'] - $sandbox['progress']) / $sandbox['total_count'];
  }
  return t('@number nodes were updated', ['@number' => $sandbox['updated_count']]);
}
