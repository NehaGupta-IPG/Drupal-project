<?php

namespace Drupal\mongodb\EntityQuery;

use Daffie\SqlLikeToRegularExpression;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\Sql\Query as CoreQuery;
use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\mongodb\Driver\Database\mongodb\MongodbSQLException;
use MongoDB\BSON\UTCDateTime;

/**
 * The MongoDB implementation of \Drupal\Core\Entity\Query\Sql\Query.
 */
class Query extends CoreQuery {

  use EntityFieldManagerTrait;
  use EntityTypeManagerTrait;

  /**
   * The build MongoDB select query.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\Select
   */
  protected $mongodbSelect;

  /**
   * An array of fields keyed by the field alias.
   *
   * Each entry correlates to the arguments of
   * \Drupal\Core\Database\Query\SelectInterface::addField(), so the first one
   * is the table alias, the second one the field and the last one optional the
   * field alias.
   *
   * @var array
   */
  protected $mongodbFields = [];

  /**
   * An array of strings added as to the group by.
   *
   * Keyed by the string to avoid duplicates.
   *
   * @var array
   */
  protected $mongodbGroupBy = [];

  /**
   * The MongoDB field to use as the id field for the query.
   *
   * @var string
   */
  protected $mongodbIdField;

  /**
   * The MongoDB field to use as the revision field for the query.
   *
   * @var string
   */
  protected $mongodbRevisionField;

  /**
   * {@inheritdoc}
   */
  protected function prepare() {
    if (!$base_table = $this->entityType->getBaseTable()) {
      throw new QueryException("No base table for " . $this->entityTypeId . ", invalid query.");
    }

    if ($this->allRevisions && !$this->entityType->isRevisionable()) {
      throw new QueryException("No revision table for " . $this->entityTypeId . ", invalid query.");
    }

    $simple_query = TRUE;
    if ($this->entityType->isTranslatable() || $this->entityType->isRevisionable()) {
      $simple_query = FALSE;
    }

    $this->mongodbSelect = $this->connection->select($base_table, 'base_table', ['conjunction' => $this->conjunction]);
    $this->mongodbSelect->addMetaData('entity_type', $this->entityTypeId);
    $id_field = $this->entityType->getKey('id');
    // Add the key field for fetchAllKeyed().
    if (!$revision_field = $this->entityType->getKey('revision')) {
      // When there is no revision support, the key field is the entity key.
      $this->mongodbFields[$id_field] = $id_field;
      $this->mongodbIdField = $id_field;
      // Now add the value column for fetchAllKeyed(). This is always the
      // entity id.
      $this->mongodbFields[$id_field . '_1'] = $id_field;
      $this->mongodbRevisionField = $id_field . '_1';
    }
    else {
      /** @var \Drupal\mongodb\EntityStorage\ContentEntityStorage $storage */
      $storage = $this->getEntityTypeManager()->getStorage($this->entityTypeId);

      if ($this->allRevisions) {
        $all_revisions_table = $storage->getJsonStorageAllRevisionsTable();
        // When there is revision support, the key field is the revision key.
        $this->mongodbFields[$all_revisions_table] = $all_revisions_table;
        $this->mongodbRevisionField = $all_revisions_table;
      }
      else {
        // When there is revision support, the key field is the revision key.
        $this->mongodbFields[$revision_field] = $revision_field;
        $this->mongodbRevisionField = $revision_field;
      }
      // Now add the value column for fetchAllKeyed(). This is always the
      // entity id.
      $this->mongodbFields[$id_field] = $id_field;
      $this->mongodbIdField = $id_field;
    }
    if ($this->accessCheck) {
      $this->mongodbSelect->addTag($this->entityTypeId . '_access');
    }
    $this->mongodbSelect->addTag('entity_query');
    $this->mongodbSelect->addTag('entity_query_' . $this->entityTypeId);

    // Add further tags added.
    if (isset($this->alterTags)) {
      foreach ($this->alterTags as $tag => $value) {
        $this->mongodbSelect->addTag($tag);
      }
    }

    // Add further metadata added.
    if (isset($this->alterMetaData)) {
      foreach ($this->alterMetaData as $key => $value) {
        $this->mongodbSelect->addMetaData($key, $value);
      }
    }
    // This now contains first the table containing entity properties and
    // last the entity base table. They might be the same.
    $this->mongodbSelect->addMetaData('all_revisions', $this->allRevisions);
    $this->mongodbSelect->addMetaData('simple_query', $simple_query);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function compile() {
    $this->condition->compile($this->mongodbSelect);
    return $this;
  }

  /**
   * Get the MongoDB full field name.
   *
   * @param string $field_name
   *   The Drupal field name.
   *
   * @return string
   *   Returns the full field name including embedded table names.
   */
  protected function getMongodbFieldName($field_name) {
    $id_field = $this->entityType->getKey('id');
    if ($field_name == $id_field) {
      return $id_field;
    }

    $bundle_field = $this->entityType->getKey('bundle');
    if ($field_name == $bundle_field) {
      return $bundle_field;
    }

    $uuid_field = $this->entityType->getKey('uuid');
    if ($field_name == $uuid_field) {
      return $uuid_field;
    }

    $revision_field = $this->entityType->getKey('revision');
    if ($field_name == $revision_field) {
      return $revision_field;
    }

    $langcode_field = $this->entityType->getKey('langcode');
    if ($field_name == $langcode_field) {
      return $langcode_field;
    }

    /** @var \Drupal\mongodb\EntityStorage\ContentEntityStorage $storage */
    $storage = $this->getEntityTypeManager()->getStorage($this->entityTypeId);

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping();

    $field_storage_definitions = $this->getEntityFieldManager()->getFieldStorageDefinitions($this->entityTypeId);

    $specifiers = explode('.', $field_name);
    $count = count($specifiers) - 1;
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

      $data_table = NULL;
      if ($field_storage && $field_storage->isRevisionable() && $this->allRevisions) {
        $data_table = $storage->getJsonStorageAllRevisionsTable();
      }
      elseif ($field_storage && $field_storage->isRevisionable() && !$this->allRevisions) {
        $data_table = $storage->getJsonStorageCurrentRevisionTable();
      }
      elseif (!$this->entityType->isRevisionable() && $this->entityType->isTranslatable()) {
        $data_table = $storage->getJsonStorageTranslationsTable();
      }
      elseif ($field_storage && $field_storage->isTranslatable()) {
        $data_table = $storage->getJsonStorageTranslationsTable();
      }

      // Check whether this field is stored in a dedicated table.
      if ($field_storage && $table_mapping->requiresDedicatedTableStorage($field_storage)) {
        $embedded_to_table = is_null($data_table) ? $storage->getBaseTable() : $data_table;
        $dedicated_table = $table_mapping->getJsonStorageDedicatedTableName($field_storage, $embedded_to_table);

        // Find the field column.
        $column = $field_storage->getMainPropertyName();
        if ($key < $count) {
          $next = $specifiers[$key + 1];
          // If this is a numeric specifier we're adding a condition on the
          // specific delta.
          if (is_numeric($next)) {
            // $delta = $next;
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
            // $relationship_specifier = $specifiers[$key + 1];
            // $propertyDefinitions = $field_storage->getPropertyDefinitions().
            //
            // Prepare the next index prefix.
            // $next_index_prefix = "$relationship_specifier.$column".
          }
        }

        $mongodb_column = $table_mapping->getFieldColumnName($field_storage, $column);

        if ($data_table) {
          return "$data_table.$dedicated_table.$mongodb_column";
        }
        else {
          return "$dedicated_table.$mongodb_column";
        }
      }
      // The field is stored in a shared table.
      else {
        $mongodb_column = $specifier;

        // If there are more specifiers, get the right MongoDB column name if
        // the next one is a column of this field.
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
              return 0;
            }
          }
          // If this is a numeric specifier we're adding a condition on the
          // specific delta. Since we know that this is a single value base
          // field no other value than 0 makes sense.
          if (is_numeric($next)) {
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

        if ($data_table) {
          return "$data_table.$mongodb_column";
        }
        else {
          return "$mongodb_column";
        }
      }
    }
  }

  /**
   * Adds the sort to the build query.
   *
   * @return \Drupal\Core\Entity\Query\Sql\Query
   *   Returns the called object.
   */
  protected function addSort() {
    if ($this->count) {
      $this->sort = [];
    }

    // Gather the MongoDB field aliases first to make sure every field table
    // necessary is added. This might change whether the query is simple or
    // not. See below for more on simple queries.
    if ($this->sort) {
      foreach ($this->sort as $sort_key => $data) {
        $field_name = $data['field'];
        $mongodb_field_name = $this->getMongodbFieldName($field_name);
        $this->sort[$sort_key]['mongodb_field'] = $mongodb_field_name;

        $has_group_by_field = FALSE;
        foreach ($this->groupBy as $group_by) {
          if (($group_by['field'] == $field_name) || ($group_by['field'] == $mongodb_field_name)) {
            $has_group_by_field = TRUE;
          }
        }
        if (!$has_group_by_field) {
          $this->mongodbFields[str_replace('.', '_', $field_name)] = $mongodb_field_name;
        }

        $this->mongodbSelect->orderBy($mongodb_field_name, $data['direction']);
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    $this->initializePager();
    foreach ($this->mongodbGroupBy as $alias => $field) {
      $this->mongodbSelect->groupByAlias($alias, $field);
    }
    foreach ($this->mongodbFields as $field_alias => $field_name) {
      $this->mongodbSelect->addField('base_table', $field_name, $field_alias);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function result() {
    if ($this->count) {
      try {
        if ($this->allRevisions) {
          $entities = $this->mongodbSelect->execute()->fetchAll();
          $count = 0;
          $all_revisions_table = \Drupal::entityTypeManager()->getStorage($this->entityType->id())->getJsonStorageAllRevisionsTable();
          foreach ($entities as $entity) {
            $count += count($entity->$all_revisions_table);
          }
          return $count;
        }
        else {
          return $this->mongodbSelect->countQuery()->execute()->fetchField();
        }
      }
      catch (MongodbSQLException) {
        // With a relational database you can create queries that will result in
        // an exception when used in MongoDB. For instance embedded table data
        // queries are combined with the MongoDB operator "$elemMatch". That
        // operator allows each combination variable and operator to be used
        // only once. So the variable color cannot be tested to be equal to blue
        // and equal to red.
        return 0;
      }
    }

    try {
      if ($this->allRevisions) {
        // For an entity query with the options all revisions the returned
        // revision_id must be located from the embedded all revisions table.
        // All other entity queries return the current revision_id from the base
        // table.
        $all_revisions_table = \Drupal::entityTypeManager()->getStorage($this->entityType->id())->getJsonStorageAllRevisionsTable();
        $revision_field = $this->entityType->getKey('revision');
        $id_field = $this->entityType->getKey('id');

        $entity_ids = [];
        $results = $this->mongodbSelect->execute()->fetchAll();
        $condition = $this->mongodbSelect->cloneCondition();

        $latest_revision_ids = [];
        foreach ($results as $result) {
          $entity_id = $result->{$id_field};
          $revisions = $result->{$all_revisions_table};
          if (is_array($revisions)) {
            foreach ($revisions as $revision) {
              if ($revision_id = $this->testRevisionForCondition($condition, $all_revisions_table, $revision)) {
                $entity_ids[$revision_id] = $entity_id;
                if (!isset($latest_revision_ids[$entity_id]) || ($latest_revision_ids[$entity_id] < $revision_id)) {
                  $latest_revision_ids[$entity_id] = $revision_id;
                }
              }
            }
          }
        }

        if ($this->latestRevision) {
          $entity_ids = array_flip($latest_revision_ids);
          foreach ($entity_ids as &$entity_id) {
            $entity_id = (string) $entity_id;
          }
        }

        // Sort the entity ids by revision id only. Yes, this is a hack.
        if (!empty($this->sort) && (count($this->sort) == 1) && ($this->sort[0]['field'] == $revision_field)) {
          if (strtoupper($this->sort[0]['direction']) == 'DESC') {
            krsort($entity_ids);
          }
          else {
            ksort($entity_ids);
          }
        }

        if ($this->range) {
          $entity_ids = array_slice($entity_ids, $this->range['start'], $this->range['length'], TRUE);
        }

        // Return a keyed array of results. The key is the revision_id and the
        // value is the entity id.
        return $entity_ids;
      }
      else {
        if (empty($this->sort)) {
          // Return a keyed array of results. The key is either the revision_id
          // or the entity_id depending on whether the entity type supports
          // revisions. The value is always the entity id.
          $results = $this->mongodbSelect->execute()->fetchAll();

          $entities = [];
          foreach ($results as $record) {
            if (isset($record->{$this->mongodbRevisionField}) && isset($record->{$this->mongodbIdField})) {
              $entities[$record->{$this->mongodbRevisionField}] = $record->{$this->mongodbIdField};
            }
          }
          return $entities;
        }
        else {
          $results = $this->mongodbSelect->execute()->fetchAll();
          $results = $this->sortQueryResult($results);
          if ($this->range) {
            // We also need the keys.
            $keys = array_slice(array_flip($results), $this->range['start'], $this->range['length']);
            $results = array_intersect_key($results, array_flip($keys));
          }
          return $results;
        }
      }
    }
    catch (MongodbSQLException) {
      // With a relational database you can create queries that will result in
      // an exception when used in MongoDB. For instance embedded table data
      // queries are combined with the MongoDB operator "$elemMatch". That
      // operator allows each combination variable and operator to be used only
      // once. So the variable color cannot be tested to be equal to blue and
      // equal to red.
      return [];
    }
  }

  /**
   * Helper method to check if the revision matches the conditions.
   *
   * @param Drupal\Core\Database\Query\ConditionInterface $condition_obj
   *   The conditions to test with.
   * @param string $embedded_table
   *   The embedded table name.
   * @param array $revision
   *   The revision to test for.
   *
   * @return int|null
   *   The revision_id is the revision matches the conditions or NULL if it does
   *   not.
   */
  protected function testRevisionForCondition(&$condition_obj, $embedded_table, $revision) {
    $revision_field = $this->entityType->getKey('revision');
    $conditions = $condition_obj->conditions();
    $conjunction = strtoupper($conditions['#conjunction']);
    unset($conditions['#conjunction']);

    $results = [];
    foreach ($conditions as $condition) {
      // Get the revision value to test for.
      $revision_value = $revision;
      $has_revision_value = TRUE;
      if (is_string($condition['field'])) {
        $field_parts = explode('.', $condition['field']);
        if ($embedded_table == $field_parts[0]) {
          array_shift($field_parts);
        }
        if (isset($field_parts[0]) && isset($revision_value[$field_parts[0]])) {
          $revision_value = $revision_value[$field_parts[0]];
          array_shift($field_parts);
        }
        else {
          $has_revision_value = FALSE;
        }
        if (isset($field_parts[0]) && is_array($revision_value)) {
          $dedicated_values = $revision_value;
          $revision_value = [];
          foreach ($dedicated_values as $dedicated_value) {
            if (isset($dedicated_value[$field_parts[0]])) {
              $revision_value[] = $dedicated_value[$field_parts[0]];
            }
          }
          if (count($revision_value) == 1) {
            $revision_value = reset($revision_value);
          }
          if (empty($revision_value)) {
            $has_revision_value = FALSE;
          }
        }
      }

      if (!$has_revision_value) {
        $results[] = FALSE;
      }
      else {
        if ($condition['value'] instanceof UTCDateTime) {
          $condition['value'] = (int) $condition['value']->__toString();
          $condition['value'] = $condition['value'] / 1000;
          $condition['value'] = (string) $condition['value'];
        }

        if (!is_array($condition['value'])) {
          $condition['value'] = [$condition['value']];
        }

        if (in_array($condition['operator'], ['=', '<', '>', '<=', '>=', '<>'], TRUE) && (reset($condition['value']) === NULL)) {
          $results[] = FALSE;
        }
        else {
          switch ($condition['operator']) {
            case '=':
              if ($revision_value == reset($condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case '<':
              if ($revision_value < reset($condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case '>':
              if ($revision_value > reset($condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case '<=':
              if ($revision_value <= reset($condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case '>=':
              if ($revision_value >= reset($condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case '<>':
              if ($revision_value != reset($condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case 'IN':
              // Do not set the third parameter of in_array() because you will
              // get the wrong result when you compare string numbers with
              // integers.
              if (in_array($revision_value, $condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case 'NOT IN':
              // Do not set the third parameter of in_array() because you will
              // get the wrong result when you compare string numbers with
              // integers.
              if (!in_array($revision_value, $condition['value'])) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case 'BETWEEN':
              $bottom_value = reset($condition['value']);
              $top_value = next($condition['value']);
              if (($revision_value >= $bottom_value) && ($revision_value <= $top_value)) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case 'NOT BETWEEN':
              $bottom_value = reset($condition['value']);
              $top_value = next($condition['value']);
              if (($revision_value < $bottom_value) || ($revision_value > $top_value)) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case 'IS NULL':
              if (is_null($revision_value)) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case 'IS NOT NULL':
              if (!is_null($revision_value)) {
                $results[] = TRUE;
              }
              else {
                $results[] = FALSE;
              }
              break;

            case 'LIKE':
            case 'NOT LIKE':
            case 'REGEXP':
            case 'NOT REGEXP':
              $pattern = reset($condition['value']);
              if (($condition['operator'] == 'LIKE') || ($condition['operator'] == 'NOT LIKE')) {
                $pattern = SqlLikeToRegularExpression::convert($pattern);
              }
              if (preg_match("/{$pattern}/", $revision_value)) {
                if (($condition['operator'] == 'NOT LIKE') || ($condition['operator'] == 'NOT REGEXP')) {
                  $results[] = FALSE;
                }
                else {
                  $results[] = TRUE;
                }
              }
              else {
                if (($condition['operator'] == 'LIKE') || ($condition['operator'] == 'REGEXP')) {
                  $results[] = FALSE;
                }
                else {
                  $results[] = TRUE;
                }
              }
              break;

            default:
              $results[] = FALSE;
              break;

          }
        }
      }
    }

    if ($conjunction == 'AND' && in_array(FALSE, $results, TRUE) === FALSE) {
      return $revision[$revision_field] ?? NULL;
    }
    if ($conjunction == 'OR' && !(in_array(TRUE, $results, TRUE) === FALSE)) {
      return $revision[$revision_field] ?? NULL;
    }
  }

  /**
   * Sorts the query results.
   *
   * @param array $results
   *   The array with the query results.
   *
   * @return array
   *   Returns the sorted array with the entity ids.
   */
  protected function sortQueryResult(array $results) {
    // The first value of the variable $this->mongodbFields is the field name
    // to be used as the key in the return array $entity_ids.
    $field_name_entity_key = reset($this->mongodbFields);

    // The second value of the variable $this->mongodbFields is the field name
    // of the entity_id to be used as the value in the return array
    // $entity_ids.
    $field_name_entity_id = next($this->mongodbFields);

    // This array holds all the parameters that will be used by the function
    // array_multisort.
    $multisort_parameters = [];

    // The array that holds all entity ids that will be sorted by
    // array_multisort.
    $associative_key_entity_ids = [];

    foreach ($this->sort as $sort) {
      // The array $sort_values holds for a particular to be sorted column the
      // significant value for each result set.
      $sort_values = [];

      // The MongoDB field name for which to do the sorting.
      $mongodb_field_name = $sort['mongodb_field'];
      $mongodb_field_name_parts = explode('.', $mongodb_field_name);
      $aliased_field_name = str_replace('.', '_', $sort['field']);
      foreach ($results as $result) {
        // The array $result_values holds all values that a to be sorted column
        // has for a particular result set.
        $result_values = [];
        if (count($mongodb_field_name_parts) == 1) {
          if (isset($result->{$mongodb_field_name})) {
            $result_values[] = $result->{$mongodb_field_name};
          }
        }
        elseif (count($mongodb_field_name_parts) == 2) {
          if (isset($result->{$aliased_field_name})) {
            $embedded_table_values = $result->{$aliased_field_name};
            foreach ($embedded_table_values as $embedded_table_value) {
              if (isset($embedded_table_value)) {
                $result_values[] = $embedded_table_value;
              }
            }
          }
        }
        elseif (count($mongodb_field_name_parts) == 3) {
          if (isset($result->{$aliased_field_name})) {
            $dedicated_table_rows = $result->{$aliased_field_name};
            foreach ($dedicated_table_rows as $dedicated_table_row) {
              foreach ($dedicated_table_row as $dedicated_table_value) {
                if (isset($dedicated_table_value)) {
                  $result_values[] = $dedicated_table_value;
                }
              }
            }
          }
        }
        if (empty($result_values)) {
          $sort_values[] = NULL;
        }
        else {
          if (isset($sort['direction']) && (strtoupper($sort['direction']) == 'DESC')) {
            rsort($result_values);
          }
          else {
            sort($result_values);
          }
          $sort_values[] = reset($result_values);
        }

        // Numeric keys will be re-indexed with the function array_multisort.
        // We need the keys.
        $associative_key_entity_ids[$field_name_entity_key . '__' . $result->{$field_name_entity_key}] = $result->{$field_name_entity_id};
      }

      // The calling of function array_multisort with the function
      // call_user_func_array works only if the parameters array's are added
      // "by reference".
      $multisort_parameters[] = $sort_values;
      $multisort_parameters[] = strtoupper($sort['direction']) == 'DESC' ? SORT_DESC : SORT_ASC;
    }

    // Add the array $associative_key_entity_ids as last to the multisort
    // parameters list. It will then be sorted the same way as the preceding
    // array's.
    $multisort_parameters[] = &$associative_key_entity_ids;

    // We must call the function array_multisort, because we cannot know with
    // how many sorting variable we are working.
    call_user_func_array('array_multisort', $multisort_parameters);

    // The array $associative_key_entity_ids has now been sorted.
    $entity_ids = [];
    foreach ($associative_key_entity_ids as $associative_key => $entity_id) {
      $entity_ids[substr($associative_key, strlen($field_name_entity_key) + 2)] = (string) $entity_id;
    }
    return $entity_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function __clone() {
    parent::__clone();
    $this->mongodbFields = [];
    $this->mongodbGroupBy = [];
  }

}
