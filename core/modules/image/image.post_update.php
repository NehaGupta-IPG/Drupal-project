<?php

/**
 * @file
 * Post-update functions for Image.
 */

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Implements hook_removed_post_updates().
 */
function image_removed_post_updates(): array {
  return [
    'image_post_update_image_style_dependencies' => '9.0.0',
    'image_post_update_scale_and_crop_effect_add_anchor' => '9.0.0',
    'image_post_update_image_loading_attribute' => '10.0.0',
  ];
}

/**
 * Add a table column for "Enable Display field" checkbox.
 */
function image_post_update_add_display_checkbox(&$sandbox = []): void {
  $properties = ['display'];

  foreach ($properties as $property) {
    $manager = Drupal::entityDefinitionUpdateManager();
    $field_map = Drupal::service('entity_field.manager')->getFieldMapByFieldType('image');

    foreach ($field_map as $entity_type_id => $fields) {
      foreach (array_keys($fields) as $field_name) {
        $field_storage_definition = $manager->getFieldStorageDefinition($field_name, $entity_type_id);
        if ($field_storage_definition) {
          $storage = Drupal::entityTypeManager()->getStorage($entity_type_id);

          if ($storage instanceof SqlContentEntityStorage) {
            $table_mapping = $storage->getTableMapping([
              $field_name => $field_storage_definition,
            ]);
            $table_names = $table_mapping->getDedicatedTableNames();
            $columns = $table_mapping->getColumnNames($field_name);
            $schema = Drupal::database()->schema();

            foreach ($table_names as $table_name) {
              $field_schema = $field_storage_definition->getSchema();
              $field_exists = $schema->fieldExists($table_name, $columns[$property]);
              $table_exists = $schema->tableExists($table_name);

              if (!$field_exists && $table_exists) {
                $schema->addField($table_name, $columns[$property], $field_schema['columns'][$property]);
              }
            }
          }
          $manager->updateFieldStorageDefinition($field_storage_definition);
        }
      }
    }
  }
}
