<?php

/**
 * @file
 * Post update functions for the Content Moderation module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function content_moderation_removed_post_updates(): array {
  return [
    'content_moderation_post_update_update_cms_default_revisions' => '9.0.0',
    'content_moderation_post_update_set_default_moderation_state' => '9.0.0',
    'content_moderation_post_update_set_views_filter_latest_translation_affected_revision' => '9.0.0',
    'content_moderation_post_update_entity_display_dependencies' => '9.0.0',
    'content_moderation_post_update_views_field_plugin_id' => '9.0.0',
    'content_moderation_post_update_add_index_content_moderation_state_field_revision_moderation_state' => '12.0.0',
  ];
}

/**
 * Make content_entity_type_id ASCII and case-sensitive.
 *
 * @see \Drupal\content_moderation\Entity\ContentModerationState::baseFieldDefinitions()
 */
function content_moderation_post_update_content_entity_type_id_schema(): void {
  $entity_type_id = 'content_moderation_state';
  $field_name = 'content_entity_type_id';

  // The field manager provides the current definition. The definition update
  // manager returns the last installed definition, whose schema was computed
  // before this change.
  $storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
  $storage_definition = $storage_definitions[$field_name];

  $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
  $table_mapping = $storage->getTableMapping($storage_definitions);
  $column_name = $table_mapping->getColumnNames($field_name)['value'];
  $specification = $storage_definition->getColumns()['value'];

  $schema = \Drupal::database()->schema();
  foreach ($table_mapping->getAllFieldTableNames($field_name) as $table) {
    $schema->changeField($table, $column_name, $column_name, $specification);
  }

  $storage_definition->setSetting('column_changes_handled', TRUE);
  \Drupal::entityDefinitionUpdateManager()->updateFieldStorageDefinition($storage_definition);
}
