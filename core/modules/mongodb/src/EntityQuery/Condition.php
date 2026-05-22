<?php

namespace Drupal\mongodb\EntityQuery;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\Sql\Condition as CoreCondition;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;

/**
 * The MongoDB implementation of \Drupal\Core\Entity\Query\Sql\Condition.
 */
class Condition extends CoreCondition {

  use EntityFieldManagerTrait;
  use EntityTypeManagerTrait;

  /**
   * The MongoDB entity query object this condition belongs to.
   *
   * @var \Drupal\mongodb\EntityQuery\Query
   */
  protected $mongodbQuery;

  /**
   * {@inheritdoc}
   */
  public function compile($conditionContainer) {
    // If this is not the top level condition group then the MongoDB query is
    // added to the $conditionContainer object by this function itself. The
    // MongoDB query object is only necessary to pass to Query::addField() so it
    // can join tables as necessary. On the other hand, conditions need to be
    // added to the $conditionContainer object to keep grouping.
    $mongodb_query = $conditionContainer instanceof SelectInterface ? $conditionContainer : $this->mongodbQuery;
    $this->mongodbQuery = $mongodb_query;

    foreach ($this->conditions as $condition) {
      if ($condition['field'] instanceof ConditionInterface) {
        $mongodb_condition = $mongodb_query->getConnection()->condition($condition['field']->getConjunction());
        // Add the MongoDB query to the object before calling this method again.
        $condition['field']->mongodbQuery = $mongodb_query;
        $condition['field']->nestedInsideOrCondition = $this->nestedInsideOrCondition || strtoupper($this->conjunction) === 'OR';
        $condition['field']->compile($mongodb_condition);
        $conditionContainer->condition($mongodb_condition);
      }
      else {
        $this->addConditions($conditionContainer, $condition);
      }
    }
  }

  /**
   * Add the condition to the condition container.
   *
   * @param array $conditionContainer
   *   The condition container.
   * @param array $condition
   *   The condition array.
   */
  public function addConditions(&$conditionContainer, &$condition) {
    $entityTypeId = $this->mongodbQuery->getMetaData('entity_type');
    $allRevisions = $this->mongodbQuery->getMetaData('all_revisions');

    // This variable ensures grouping works correctly. For example:
    // ->condition('tags', 2, '>')
    // ->condition('tags', 20, '<')
    // ->condition('node_reference.nid.entity.tags', 2)
    // The first two should use the same table but the last one needs to be a
    // new table. So for the first two, the table array index will be 'tags'
    // while the third will be 'node_reference.nid.tags'.
    // $index_prefix = ''.
    $specifiers = explode('.', $condition['field']);
    $count = count($specifiers) - 1;
    // This will contain the definitions of the last specifier seen by the
    // system.
    $propertyDefinitions = [];
    $entity_type = $this->getEntityTypeManager()->getDefinition($entityTypeId);

    $field_storage_definitions = $this->getEntityFieldManager()->getFieldStorageDefinitions($entityTypeId);
    for ($key = 0; $key <= $count; $key++) {
      // This can either be the name of an entity base field or a configurable
      // field.
      $specifier = $specifiers[$key];
      if (isset($field_storage_definitions[$specifier])) {
        $field_storage = $field_storage_definitions[$specifier];
      }
      else {
        $field_storage = FALSE;
      }

      // If there is revision support, only the current revisions are being
      // queried, and the field is revisionable then use the revision id.
      // Otherwise, the entity id will do.
      // if (($revision_key = $entity_type->getKey('revision')) &&
      // $allRevisions && $field_storage && $field_storage->isRevisionable()) {
      // This contains the relevant SQL field to be used when joining entity
      // tables.
      // $entity_id_field = $revision_key;
      // $field_id_field = 'revision_id';
      // }
      // else {
      // This contains the relevant SQL field to be used when joining field
      // tables.
      // $entity_id_field = $entity_type->getKey('id');
      // $field_id_field = 'entity_id';
      // }.

      /** @var \Drupal\mongodb\EntityStorage\ContentEntityStorage $storage */
      $storage = $this->getEntityTypeManager()->getStorage($entityTypeId);

      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $storage->getTableMapping();
      // Check whether this field is stored in a dedicated table.
      if ($field_storage && $table_mapping->requiresDedicatedTableStorage($field_storage)) {
        if ($entity_type->isRevisionable() && $allRevisions) {
          $data_table = $storage->getJsonStorageAllRevisionsTable();
        }
        elseif ($entity_type->isRevisionable() && !$allRevisions) {
          $data_table = $storage->getJsonStorageCurrentRevisionTable();
        }
        else {
          $data_table = $storage->getJsonStorageTranslationsTable();
        }

        $dedicated_table = $table_mapping->getJsonStorageDedicatedTableName($field_storage, ($data_table ?: $storage->getBaseTable()));

        $delta = NULL;
        // Find the field column.
        $column = $field_storage->getMainPropertyName();
        if ($key < $count) {
          $next = $specifiers[$key + 1];
          // If this is a numeric specifier we're adding a condition on the
          // specific delta.
          if (is_numeric($next)) {
            $delta = $next;
            // $index_prefix .= ".$delta";
            // Do not process it again.
            $key++;
            $next = $specifiers[$key + 1];
          }
          // If this specifier is the reserved keyword "%delta" we're adding a
          // condition on a delta range.
          elseif ($next == TableMappingInterface::DELTA) {
            // $index_prefix .= TableMappingInterface::DELTA;
            // Do not process it again.
            $key++;
            // If there are more specifiers to work with then continue
            // processing. If this is the last specifier then use the reserved
            // keyword as a column name.
            if ($key < $count) {
              $next = $specifiers[$key + 1];
            }
            else {
              $column = TableMappingInterface::DELTA;
            }
          }
          // Is this a field column?
          $columns = $field_storage->getColumns();
          if (isset($columns[$next]) || in_array($next, $table_mapping->getReservedColumns())) {
            // Use it.
            $column = $next;
            // Do not process it again.
            $key++;
          }
          // If there are more specifiers, the next one must be a
          // relationship. Either the field name followed by a relationship
          // specifier, for example $node->field_image->entity. Or a field
          // column followed by a relationship specifier, for example
          // $node->field_image->fid->entity. In both cases, prepare the
          // property definitions for the relationship. In the first case,
          // also use the property definitions for column.
          if ($key < $count) {
            $relationship_specifier = $specifiers[$key + 1];
            $propertyDefinitions = $field_storage->getPropertyDefinitions();

            // Prepare the next index prefix.
            // $next_index_prefix = "$relationship_specifier.$column".
          }
        }
        $mongodb_column = $table_mapping->getFieldColumnName($field_storage, $column);
        $conditions = $conditionContainer->conditions();
        $conjunction = strtoupper($conditions['#conjunction']);

        // Determine if the current condition field is case sensitive.
        $case_sensitive_field = FALSE;
        $property_definitions = $field_storage->getPropertyDefinitions();
        if (isset($property_definitions[$column])) {
          $case_sensitive_field = $property_definitions[$column]->getSetting('case_sensitive');
        }

        $field_schema = \Drupal::service('mongodb.table_information')->getTableField($dedicated_table, $mongodb_column);

        // A condition on the deleted value of a field should not be translated.
        if (!isset($field_schema['type']) || (isset($field_schema['type']) && !in_array($field_schema['type'], ['int', 'serial', 'bool'], TRUE))) {
          // Translate the string condition operators to something that MongoDB
          // can handle. But only do this for non-integer fields.
          static::translateCondition($condition, $this->mongodbQuery, $case_sensitive_field);
        }

        $field_type = $field_storage ? $field_storage->getType() : NULL;
        // MongoDB expects integer values to be real integers.
        if ($field_type == 'integer') {
          if (is_array($condition['value'])) {
            foreach ($condition['value'] as &$condition_value) {
              $condition_value = (int) $condition_value;
            }
            unset($condition_value);
          }
          else {
            $condition['value'] = (int) $condition['value'];
          }
        }
        elseif ($field_type == 'boolean') {
          if (is_array($condition['value'])) {
            foreach ($condition['value'] as &$condition_value) {
              if ($field_schema['type'] == 'int') {
                $condition_value = $condition_value ? 1 : 0;
              }
              else {
                $condition_value = (bool) $condition_value;
              }
            }
            unset($condition_value);
          }
          else {
            if ($field_schema['type'] == 'int') {
              $condition['value'] = $condition['value'] ? 1 : 0;
            }
            else {
              $condition['value'] = (bool) $condition['value'];
            }
          }
        }

        if (isset($condition['langcode']) && $conjunction == 'OR' && $data_table) {
          $condition_and = $this->mongodbQuery->getConnection()->condition('AND');
          $condition_and->condition("$data_table.$dedicated_table.$mongodb_column", $condition['value'], $condition['operator']);
          $condition_and->condition("$data_table.$dedicated_table.langcode", $condition['langcode'], '=');
          if (!is_null($delta)) {
            $condition_and->condition("$data_table.$dedicated_table.delta", intval($delta), '=');
          }
          $conditionContainer->condition($condition_and);
        }
        elseif (isset($condition['langcode']) && $conjunction == 'OR') {
          $condition_and = $this->mongodbQuery->getConnection()->condition('AND');
          $condition_and->condition("$dedicated_table.$mongodb_column", $condition['value'], $condition['operator']);
          $condition_and->condition("$dedicated_table.langcode", $condition['langcode'], '=');
          if (!is_null($delta)) {
            $condition_and->condition("$dedicated_table.delta", intval($delta), '=');
          }
          $conditionContainer->condition($condition_and);
        }
        elseif ($data_table) {
          $conditionContainer->condition("$data_table.$dedicated_table.$mongodb_column", $condition['value'], $condition['operator']);
          if (isset($condition['langcode'])) {
            $conditionContainer->condition("$data_table.$dedicated_table.langcode", $condition['langcode'], '=');
          }
          if (!is_null($delta)) {
            $conditionContainer->condition("$data_table.$dedicated_table.delta", intval($delta), '=');
          }
        }
        else {
          $conditionContainer->condition("$dedicated_table.$mongodb_column", $condition['value'], $condition['operator']);
          if (isset($condition['langcode'])) {
            $conditionContainer->condition("$dedicated_table.langcode", $condition['langcode'], '=');
          }
          if (!is_null($delta)) {
            $conditionContainer->condition("$dedicated_table.delta", intval($delta), '=');
          }
        }
      }
      // The field is stored in a shared table.
      else {
        // Check whether this field is stored in a dedicated table.
        $data_table = NULL;
        if ($entity_type->isRevisionable() && $allRevisions) {
          $data_table = $storage->getJsonStorageAllRevisionsTable();
        }
        elseif ($entity_type->isRevisionable() && !$allRevisions) {
          $data_table = $storage->getJsonStorageCurrentRevisionTable();
        }
        else {
          $data_table = $storage->getJsonStorageTranslationsTable();
        }
        if ($data_table) {
          $this->mongodbQuery->addMetaData('simple_query', FALSE);
        }

        $mongodb_column = $specifier;

        // If there are more specifiers, get the right sql column name if the
        // next one is a column of this field.
        if ($key < $count) {
          $next = $specifiers[$key + 1];
          // If this specifier is the reserved keyword "%delta" we're adding a
          // condition on a delta range.
          if ($next == TableMappingInterface::DELTA) {
            $key++;
            if ($key < $count) {
              $next = $specifiers[$key + 1];
            }
            else {
              if (isset($condition['value']) && ($condition['value'] === 0) && $data_table) {
                $conditionContainer->condition("$data_table.$mongodb_column", NULL, 'IS NOT NULL');
              }
              elseif (isset($condition['value']) && ($condition['value'] === 0)) {
                $conditionContainer->condition("$mongodb_column", NULL, 'IS NOT NULL');
              }
              elseif ($data_table) {
                $conditionContainer->condition("$data_table.$mongodb_column", NULL, '=');
              }
              else {
                $conditionContainer->condition("$mongodb_column", NULL, '=');
              }
              return 0;
            }
          }

          // If this is a numeric specifier we're adding a condition on the
          // specific delta. Since we know that this is a single value base
          // field no other value than 0 makes sense.
          if (is_numeric($next)) {
            if ($next > 0) {
              $this->mongodbQuery->condition('1 <> 1');
            }
            $key++;
            $next = $specifiers[$key + 1];
          }

          // Is this a field column?
          $columns = $field_storage->getColumns();
          if (isset($columns[$next]) || in_array($next, $table_mapping->getReservedColumns())) {
            // Use it.
            $mongodb_column = $table_mapping->getFieldColumnName($field_storage, $next);
            // Do not process it again.
            $key++;
          }
        }

        // The default langcode key is by default revisionable and translatable.
        // We cannot use those parameters and we must look at the entity type.
        if ($mongodb_column == $entity_type->getKey('default_langcode')) {
          if ($entity_type->isRevisionable() && $allRevisions) {
            $data_table = $storage->getJsonStorageAllRevisionsTable();
          }
          elseif ($entity_type->isRevisionable() && !$allRevisions) {
            $data_table = $storage->getJsonStorageCurrentRevisionTable();
          }
          elseif ($entity_type->isTranslatable()) {
            $data_table = $storage->getJsonStorageTranslationsTable();
          }
        }

        if ($entity_type->isRevisionable() && !$allRevisions) {
          $data_table = $storage->getJsonStorageCurrentRevisionTable();
        }

        $conditions = $conditionContainer->conditions();
        $conjunction = strtoupper($conditions['#conjunction']);

        // If there is a field storage (some specifiers are not), check for case
        // sensitivity.
        $case_sensitive_field = FALSE;
        if ($field_storage) {
          $column = $field_storage->getMainPropertyName();
          $property_definitions = $field_storage->getPropertyDefinitions();
          if (isset($property_definitions[$column])) {
            $case_sensitive_field = $property_definitions[$column]->getSetting('case_sensitive');
          }
        }

        $field_schema = \Drupal::service('mongodb.table_information')->getTableField($data_table, $mongodb_column);
        if (!isset($field_schema['type']) || (isset($field_schema['type']) && !in_array($field_schema['type'], ['int', 'serial'], TRUE))) {
          // Translate the string condition operators to something that MongoDB
          // can handle. But only do this for non-integer fields.
          static::translateCondition($condition, $this->mongodbQuery, $case_sensitive_field);
        }

        $field_type = $field_storage ? $field_storage->getType() : NULL;
        // MongoDB expects integer values to be real integers.
        if ($field_type == 'integer') {
          if (is_array($condition['value'])) {
            foreach ($condition['value'] as &$condition_value) {
              $condition_value = (int) $condition_value;
            }
            unset($condition_value);
          }
          else {
            $condition['value'] = (int) $condition['value'];
          }
        }
        elseif ($field_type == 'boolean') {
          if (is_array($condition['value'])) {
            foreach ($condition['value'] as &$condition_value) {
              if (isset($field_schema['type']) && ($field_schema['type'] == 'int')) {
                $condition_value = $condition_value ? 1 : 0;
              }
              else {
                $condition_value = (boolean) $condition_value;
              }
            }
            unset($condition_value);
          }
          else {
            if (isset($field_schema['type']) && ($field_schema['type'] == 'int')) {
              $condition['value'] = $condition['value'] ? 1 : 0;
            }
            else {
              $condition['value'] = (boolean) $condition['value'];
            }
          }
        }

        $langcode_field = $entity_type->getKey('langcode') ?: 'langcode';
        if (isset($condition['langcode']) && $conjunction == 'OR' && $data_table) {
          $condition_and = $this->mongodbQuery->getConnection()->condition('AND');
          $condition_and->condition("$data_table.$mongodb_column", $condition['value'], $condition['operator']);
          $condition_and->condition("$data_table.$langcode_field", $condition['langcode'], '=');
          $conditionContainer->condition($condition_and);
        }
        elseif (isset($condition['langcode']) && $conjunction == 'OR') {
          $condition_and = $this->mongodbQuery->getConnection()->condition('AND');
          $condition_and->condition("$mongodb_column", $condition['value'], $condition['operator']);
          $condition_and->condition("$langcode_field", $condition['langcode'], '=');
          $conditionContainer->condition($condition_and);
        }
        elseif ($data_table) {
          $conditionContainer->condition("$data_table.$mongodb_column", $condition['value'], $condition['operator']);
          if (isset($condition['langcode'])) {
            $conditionContainer->condition("$data_table.$langcode_field", $condition['langcode'], '=');
          }
        }
        else {
          $conditionContainer->condition("$mongodb_column", $condition['value'], $condition['operator']);
          if (isset($condition[$langcode_field])) {
            $conditionContainer->condition("$langcode_field", $condition['langcode'], '=');
          }
        }
      }

      // If there are more specifiers to come, it's a relationship.
      if ($field_storage && $key < $count) {
        // Computed fields have prepared their property definition already, do
        // it for properties as well.
        if (!$propertyDefinitions) {
          $propertyDefinitions = $field_storage->getPropertyDefinitions();
          $relationship_specifier = $specifiers[$key + 1];
          // $next_index_prefix = $relationship_specifier;
        }
        $entity_type_id = NULL;
        // Relationship specifier can also contain the entity type ID, i.e.
        // entity:node, entity:user or entity:taxonomy.
        if (strpos($relationship_specifier, ':') !== FALSE) {
          [$relationship_specifier, $entity_type_id] = explode(':', $relationship_specifier, 2);
        }
        // Check for a valid relationship.
        if (isset($propertyDefinitions[$relationship_specifier]) && $propertyDefinitions[$relationship_specifier] instanceof DataReferenceDefinitionInterface) {
          // If it is, use the entity type if specified already, otherwise use
          // the definition.
          $target_definition = $propertyDefinitions[$relationship_specifier]->getTargetDefinition();
          if (!$entity_type_id && $target_definition instanceof EntityDataDefinitionInterface) {
            $entity_type_id = $target_definition->getEntityTypeId();
          }
          $entity_type = $this->getEntityTypeManager()->getDefinition($entity_type_id);
          $field_storage_definitions = $this->getEntityFieldManager()->getFieldStorageDefinitions($entity_type_id);
          // Add the new entity base table using the table and sql column.
          $propertyDefinitions = [];
          $key++;
          // $index_prefix .= "$next_index_prefix.";
        }
        else {
          throw new QueryException("Invalid specifier '$relationship_specifier'");
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function translateCondition(&$condition, SelectInterface $mongodb_query, $case_sensitive) {
    // Ensure that the default operator is set to simplify the cases below.
    if (empty($condition['operator'])) {
      $condition['operator'] = '=';
    }
    switch ($condition['operator']) {
      case '=':
        // If a field explicitly requests that queries should not be case
        // sensitive, use the LIKE operator, otherwise keep =.
        if ($case_sensitive === FALSE) {
          $condition['value'] = $mongodb_query->escapeLike($condition['value']);
          $condition['operator'] = 'LIKE';
        }
        break;

      case '<>':
        // If a field explicitly requests that queries should not be case
        // sensitive, use the NOT LIKE operator, otherwise keep <>.
        if ($case_sensitive === FALSE) {
          $condition['value'] = $mongodb_query->escapeLike($condition['value']);
          $condition['operator'] = 'NOT LIKE';
        }
        break;

      case 'STARTS_WITH':
        if ($case_sensitive) {
          $condition['operator'] = 'LIKE BINARY';
        }
        else {
          $condition['operator'] = 'LIKE';
        }
        $condition['value'] = $mongodb_query->escapeLike($condition['value']) . '%';
        break;

      case 'CONTAINS':
        if ($case_sensitive) {
          $condition['operator'] = 'LIKE BINARY';
        }
        else {
          $condition['operator'] = 'LIKE';
        }
        $condition['value'] = '%' . $mongodb_query->escapeLike($condition['value']) . '%';
        break;

      case 'ENDS_WITH':
        if ($case_sensitive) {
          $condition['operator'] = 'LIKE BINARY';
        }
        else {
          $condition['operator'] = 'LIKE';
        }
        $condition['value'] = '%' . $mongodb_query->escapeLike($condition['value']);
        break;

      case 'IN':
        if (is_array($condition['value']) && $case_sensitive === FALSE) {
          $values = [];
          foreach ($condition['value'] as $value) {
            $values[] = $mongodb_query->escapeLike($value);
          }
          $condition['value'] = $values;
          $condition['operator'] = 'IN NOT BINARY';
        }
        break;
    }
  }

  /**
   * Gets the schema for the given table.
   *
   * @param string $table
   *   The table name.
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return array|false
   *   An associative array of table field mapping for the given table, keyed by
   *   columns name and values are just incrementing integers. If the table
   *   mapping is not available, FALSE is returned.
   */
  protected function getTableMapping($table, $entity_type_id) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if ($storage instanceof SqlEntityStorageInterface) {
      $mapping = $storage->getTableMapping()->getAllColumns($table);
    }
    else {
      return FALSE;
    }
    return array_flip($mapping);
  }

}
