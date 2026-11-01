<?php

namespace Drupal\file;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the file schema handler.
 */
class FileStorageSchema extends SqlContentEntityStorageSchema {

  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $index_fields = ['fid', 'filename', 'filemime', 'filesize', 'status', 'created', 'changed'];
    $table_mapping = $this->getTableMapping($entity_type, $this->fieldStorageDefinitions);
    $tables = $this->getEntitySchemaTables($table_mapping);
    $base_table = $tables['base_table'];
    foreach ($index_fields as $key => $field) {
      if (!isset($schema[$base_table]['fields'][$field])) {
        unset($index_fields[$key]);
      }
    }
    $schema[$base_table]['indexes'][$this->getEntityIndexName($entity_type, 'admin_content_files')] = $index_fields;
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping) {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
    $field_name = $storage_definition->getName();

    if ($table_name == $this->storage->getBaseTable()) {
      switch ($field_name) {
        case 'status':
        case 'changed':
        case 'uri':
          $this->addSharedTableFieldIndex($storage_definition, $schema, TRUE);
          break;
      }
    }
    // Entity keys automatically have not null assigned to TRUE, but for the
    // file entity, NULL is a valid value for uid.
    if ($field_name === 'uid') {
      $schema['fields']['uid']['not null'] = FALSE;
    }

    return $schema;
  }

}
