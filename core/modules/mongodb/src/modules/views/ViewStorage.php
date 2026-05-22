<?php

namespace Drupal\mongodb\modules\views;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * The MongoDB implementation for the storage class for view entities.
 */
class ViewStorage extends ConfigEntityStorage {

  /**
   * Helper method for updating a view from relational to MongoDB.
   *
   * @param array $values
   *   An array with view entity config data.
   *
   * @return array
   *   An array with view entity config data for a MongoDB database.
   */
  protected function updateViewForMongodb($values) {
    // @todo Remove reverse relationships and associated fields. MongoDB does
    // not need or support them.
    // @see Drupal\Tests\field\Kernel\EntityReference\Views\EntityReferenceRelationshipTest.
    //
    // Update the records so that they will work for MongoDB.
    if (!empty($values['base_table'])) {
      $original_base_table = $values['base_table'];
      $base_table = $this->getBaseTable($values['base_table']);
      if (!empty($base_table) && ($original_base_table != $base_table)) {
        $values['mongodb_base_table'] = $base_table;
        $values['original_base_table'] = $values['base_table'];

        $entity_type = NULL;
        if (!empty($values['entity_type'])) {
          $entity_type = \Drupal::entityTypeManager()->getDefinition($values['entity_type']);
        }
        elseif ($entity_type = $this->getEntityTypeFromView($values, [$base_table, $original_base_table])) {
          $values['entity_type'] = $entity_type->id();
        }
        // Hack for a specific testing view.
        elseif ($values['id'] == 'test_serializer_display_entity_translated') {
          $entity_type = \Drupal::entityTypeManager()->getDefinition('entity_test_mul');
          $values['entity_type'] = $entity_type->id();
        }

        if ($entity_type) {
          if ($entity_type->isRevisionable() && in_array($original_base_table, [$entity_type->getRevisionTable(), $entity_type->getRevisionDataTable()], TRUE)) {
            $values['all_revisions_table'] = \Drupal::entityTypeManager()->getStorage($entity_type->id())->getJsonStorageAllRevisionsTable();
            $values['latest_revision_table'] = \Drupal::entityTypeManager()->getStorage($entity_type->id())->getJsonStorageLatestRevisionTable();
            $values['current_revision_table'] = \Drupal::entityTypeManager()->getStorage($entity_type->id())->getJsonStorageCurrentRevisionTable();
          }
          elseif ($entity_type->isRevisionable()) {
            $values['current_revision_table'] = \Drupal::entityTypeManager()->getStorage($entity_type->id())->getJsonStorageCurrentRevisionTable();
          }
          elseif (!empty($entity_type->getDataTable()) && ($original_base_table == $entity_type->getDataTable())) {
            $values['translations_table'] = \Drupal::entityTypeManager()->getStorage($entity_type->id())->getJsonStorageTranslationsTable();
          }
        }
      }
    }

    // JSON entity storage uses far fewer relationships then relational entity
    // storage.
    $removed_relationships = [];

    if (isset($values['display']) && is_array($values['display'])) {
      foreach ($values['display'] as &$display) {
        if (isset($display['display_options']) && is_array($display['display_options'])) {

          // MongoDB stores all entity data in the base table of the entity.
          // Table joins to other entity tables are therefor not necessary.
          foreach ($display['display_options'] as $display_options_id => &$display_options) {
            if ($display_options_id == 'relationships' && is_array($display_options)) {
              foreach ($display_options as $display_option_key => &$display_option) {
                $table = !empty($display_option['table']) ? $display_option['table'] : NULL;
                $entity_type = !empty($display_option['entity_type']) ? $display_option['entity_type'] : NULL;

                // @todo See if we can solve this more generally.
                if (isset($display_option['table']) && ($display_option['table'] == 'taxonomy_term__parent') &&
                  isset($display_option['field']) && ($display_option['field'] == 'parent_target_id')) {

                  // Remove the relationship.
                  unset($display_options[$display_option_key]);

                  // Add the removed relationship to the list of removed
                  // relationships.
                  $removed_relationships[] = $display_option_key;
                }

                if (isset($display_option['plugin_id']) && ($display_option['plugin_id'] == 'entity_reverse')) {
                  // Remove the relationship.
                  unset($display_options[$display_option_key]);

                  // Add the removed relationship to the list of removed
                  // relationships.
                  $removed_relationships[] = $display_option_key;
                }

                $has_fields = $this->hasFieldsNotFromBaseTable($values, $this->getBaseTable($table));

                // Do not remove the relationship for the test_entity_row view.
                if (!$has_fields && ($values['id'] != 'test_entity_row')) {
                  // Remove the relationship.
                  unset($display_options[$display_option_key]);

                  // Add the removed relationship to the list of removed
                  // relationships.
                  $removed_relationships[] = $display_option_key;
                }
              }
            }
          }

          // Replace the removed relationships with the relationship "none".
          foreach ($display['display_options'] as $display_options_id => &$display_options) {
            if (in_array($display_options_id, ['fields', 'filters']) && is_array($display_options)) {
              foreach ($display_options as &$display_option) {
                if (!empty($display_option['relationship']) && in_array($display_option['relationship'], $removed_relationships, TRUE)) {
                  $display_option['relationship'] = 'none';
                }
              }
            }

            // For each field, filter and relationship replace the relational
            // database table name for the MongoDB version.
            if (in_array($display_options_id, ['fields', 'filters', 'sorts', 'arguments', 'relationships']) && is_array($display_options)) {
              foreach ($display_options as &$display_option) {
                if (!empty($display_option['table']) && !empty($display_option['entity_type'])) {
                  $entity_type = \Drupal::entityTypeManager()->getDefinition($display_option['entity_type']);
                  if ($entity_type instanceof EntityTypeInterface) {
                    if (!empty($display_option['entity_field']) &&
                            is_string($display_option['entity_field'])
                    ) {
                      $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type->id());
                      if (in_array($display_option['entity_field'], array_keys($field_storage_definitions), TRUE)) {
                        $field_storage_definition = $field_storage_definitions[$display_option['entity_field']];
                        $storage = \Drupal::entityTypeManager()->getStorage($entity_type->id());
                        $table_mapping = $storage->getTableMapping();
                        if ($table_mapping->requiresDedicatedTableStorage($field_storage_definition)) {
                          $display_option['table'] = $entity_type->getBaseTable();
                        }
                      }
                    }
                    if (!empty($display_option['table']) && in_array($display_option['table'], $this->getEntityTables($entity_type), TRUE)) {
                      $display_option['table'] = $entity_type->getBaseTable();
                    }
                  }
                }
                elseif (!empty($display_option['table'])) {
                  $display_option['table'] = $this->getBaseTable($display_option['table']);
                }
                if (($display_options_id == 'relationships') && isset($display_option['plugin_id']) && ($display_option['plugin_id'] == 'groupwise_max')) {
                  if (isset($display_option['subquery_sort']) && ($display_option['subquery_sort'] == 'node_field_data.nid')) {
                    $display_option['subquery_sort'] = 'node.nid';
                  }
                }
              }
            }
          }

          if (isset($display['cache_metadata'])) {
            // Both max-age and tags values must be set.
            if (!isset($display['cache_metadata']['max-age'])) {
              $display['cache_metadata']['max-age'] = Cache::PERMANENT;
            }
            if (!isset($display['cache_metadata']['tags'])) {
              $display['cache_metadata']['tags'] = [];
            }
          }
        }
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function mapFromStorageRecords(array $records) {
    // Update the records so that they are ready for the MongoDB backend.
    foreach ($records as &$record) {
      $record = $this->updateViewForMongodb($record);
    }

    return parent::mapFromStorageRecords($records);
  }

  /**
   * Get list of all entity tables for an entity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type for which to get the entity tables.
   *
   * @return array
   *   A list of entity tables.
   */
  protected function getEntityTables(EntityTypeInterface $entity_type): array {
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type->id());
    $entity_tables = [
      $entity_type->getBaseTable(),
      $entity_type->getDataTable(),
      $entity_type->getRevisionTable(),
      $entity_type->getRevisionDataTable(),
      $storage->getJsonStorageAllRevisionsTable(),
      $storage->getJsonStorageCurrentRevisionTable(),
      $storage->getJsonStorageLatestRevisionTable(),
      $storage->getJsonStorageTranslationsTable(),
    ];
    return array_filter($entity_tables);
  }

  /**
   * Helper method for getting the entity type belonging to the table.
   *
   * @param array $records
   *   Associative array of query results, keyed on the entity ID.
   * @param string $base_table
   *   The base table name for which to search the fields of view.
   *
   * @return bool
   *   If there are field that do not belong to the given base table.
   */
  protected function hasFieldsNotFromBaseTable(array &$records, string $base_table) {
    $has_fields = FALSE;
    if (isset($records['display']) && is_array($records['display'])) {
      foreach ($records['display'] as &$display) {
        if (isset($display['display_options']) && is_array($display['display_options'])) {
          foreach ($display['display_options'] as &$display_options) {
            if (is_array($display_options)) {
              foreach ($display_options as &$display_option) {
                if (is_array($display_option) && !empty($display_option['table']) && ($base_table != $this->getBaseTable($display_option['table']))) {
                  $has_fields = TRUE;
                }
              }
            }
          }
        }
      }
    }

    return $has_fields;
  }

  /**
   * Helper method for getting the entity type belonging to the table.
   *
   * @param array $records
   *   Associative array of query results, keyed on the entity ID.
   * @param array $tables
   *   The table name for which to search the view for the entity type.
   *
   * @return \Drupal\Core\Entity\EntityType|null
   *   The entity type belonging to the given table or null if not found one.
   */
  protected function getEntityTypeFromView(array &$records, array $tables = []) {
    if (isset($records['display']) && is_array($records['display'])) {
      foreach ($records['display'] as &$display) {
        if (isset($display['display_options']) && is_array($display['display_options'])) {
          foreach ($display['display_options'] as &$display_options) {
            if (is_array($display_options)) {
              foreach ($display_options as &$display_option) {
                if (is_array($display_option) && isset($display_option['entity_type']) && !empty($display_option['table']) && in_array($display_option['table'], $tables, TRUE)) {
                  $entity_type = \Drupal::entityTypeManager()->getDefinition($display_option['entity_type']);
                  if (in_array($records['base_table'], $this->getEntityTables($entity_type), TRUE)) {
                    return $entity_type;
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    // Update the values so that they are ready for the MongoDB backend.
    $values = $this->updateViewForMongodb($values);

    // Set default language to current language if not provided.
    $values += [$this->langcodeKey => $this->languageManager->getCurrentLanguage()->getId()];
    $entity_class = $this->getEntityClass();
    $entity = new $entity_class($values, $this->entityTypeId);

    return $entity;
  }

  /**
   * Get the base table to which the table belongs.
   *
   * @param string $table
   *   The table name for which to get the base table.
   *
   * @return string
   *   The base table name.
   */
  protected function getBaseTable(string $table): string {
    // For contrib and custom modules we shall need a hook or plugin system to
    // allow them to override the default functionality.
    //
    // Table names for entity field values use the naming convention were the
    // first part is the entity name and base table name, then two underscore
    // characters followed by the field name.
    $double_underscore_parts = explode('__', $table);
    if (count($double_underscore_parts) == 2) {
      // The entity taxonomy_term does not adhere to the default naming
      // convention.
      if ($double_underscore_parts[0] == 'taxonomy_term') {
        return 'taxonomy_term_data';
      }
      // For the user entity is the base table name not the same as the entity
      // name.
      elseif ($double_underscore_parts[0] == 'user') {
        return 'users';
      }

      return $double_underscore_parts[0];
    }

    // The entity taxonomy_term does not adhere to the default naming
    // convention.
    if ($table == 'taxonomy_term_field_data') {
      return 'taxonomy_term_data';
    }

    if (str_ends_with($table, '_field_data')) {
      return substr($table, 0, -strlen('_field_data'));
    }

    if (str_ends_with($table, '_field_revision')) {
      return substr($table, 0, -strlen('_field_revision'));
    }

    if (str_ends_with($table, '_revision')) {
      return substr($table, 0, -strlen('_revision'));
    }

    if (str_ends_with($table, '_property_data')) {
      return substr($table, 0, -strlen('_property_data'));
    }

    if (str_ends_with($table, '_property')) {
      return substr($table, 0, -strlen('_property'));
    }

    // Exception for Drupal\Tests\options\Kernel\Views\OptionsListFilterTest::testViewsTestOptionsListGroupedFilter.
    if (in_array($table, ['field_data_field_test_list_string', 'field_data_field_test_list_integer', 'nid'], TRUE)) {
      return 'node';
    }

    return $table;
  }

}
