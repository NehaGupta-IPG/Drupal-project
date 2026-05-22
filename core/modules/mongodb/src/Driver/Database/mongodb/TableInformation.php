<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

/**
 * The MongoDB service for table information.
 */
class TableInformation {

  use DependencySerializationTrait;

  /**
   * The table that will hold all the table information.
   */
  const TABLE_NAME = 'table_information';

  /**
   * The current database connection.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\Connection
   */
  protected $connection;

  /**
   * The _id value where all table information is stored.
   *
   * @var int
   */
  protected $id = 1;

  /**
   * The cached table information.
   *
   * @var array
   */
  protected $tableInformation = [];

  /**
   * The tables that need to be saved to the database.
   *
   * @var array
   */
  protected $tablesToSave = [];

  /**
   * Constructs the TableInformation object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Loads the table information from the database.
   *
   * @param bool $reload
   *   Wether or not the table information should be reloaded from the database.
   */
  public function load($reload = FALSE) {
    if (empty($this->tableInformation) || $reload) {
      $prefixed_table_information_table = $this->connection->getPrefix() . static::TABLE_NAME;

      $result = $this->connection->getConnection()->selectCollection($prefixed_table_information_table)->findOne(
        ['_id' => $this->id],
        ['session' => $this->connection->getMongodbSession()],
      );

      if ($result && isset($result->_id)) {
        $this->tableInformation = $result;
        if (isset($this->tableInformation['_id'])) {
          unset($this->tableInformation['_id']);
        }
      }
    }
  }

  /**
   * Helper method that changes all BSONDocuments and BSONArrays to arrays.
   *
   * @param array $data
   *   The table data to be saved.
   *
   * @return array
   *   The cleaned up data.
   */
  protected function toArray($data) {
    if ($data instanceof BSONDocument) {
      $data = (array) $data;
    }
    if ($data instanceof BSONArray) {
      $data = (array) $data;
    }
    if (is_array($data)) {
      foreach ($data as &$item) {
        $item = $this->toArray($item);
      }
    }
    return $data;
  }

  /**
   * Get the all table information for the given table name.
   *
   * @param string $table
   *   The table name for which to get the data.
   *
   * @return array
   *   The table information for the given table.
   */
  public function getTable($table) {
    $this->load();
    if (isset($this->tableInformation[$table])) {
      return $this->toArray($this->tableInformation[$table]);
    }
    return [];
  }

  /**
   * Get the all embedded table information for the given table name.
   *
   * @param string $table
   *   The embedded table name for which to get the data.
   *
   * @return array
   *   The table information for the given embedded table.
   */
  public function getEmbeddedTable($table) {
    return $this->getTable($table);
  }

  /**
   * Set the table information for the table to the given spec.
   *
   * @param string $table
   *   The table name for which to set the data.
   * @param array $spec
   *   The specs to set.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTable(string $table, array $spec = []) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table])) {
        unset($this->tableInformation[$table]);
      }
    }
    else {
      $this->tableInformation[$table] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Set the embedded table information for the table to the given spec.
   *
   * @param string $table
   *   The table name for which to set the data.
   * @param array $spec
   *   The specs to set.
   * @param string $embedded_to_table
   *   The parent table name for which to set the data.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setEmbeddedTable($table, $spec, $embedded_to_table) {
    $this->load();
    if (!empty($spec) && !empty($embedded_to_table)) {
      $this->tableInformation[$table] = $spec;
      $this->tableInformation[$table]['embedded_to_table'] = $embedded_to_table;
    }
    elseif (isset($this->tableInformation[$table])) {
      unset($this->tableInformation[$table]);
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table name to which the given table is embedded.
   *
   * @param string $table
   *   The table name for which to get it's parents table name.
   *
   * @return string|false
   *   The table name to which the given table name is embedded or FALSE when
   *   the table is a base table.
   */
  public function getTableEmbeddedToTable($table) {
    $this->load();
    if (isset($this->tableInformation[$table]['embedded_to_table'])) {
      return $this->toArray($this->tableInformation[$table]['embedded_to_table']);
    }
    return FALSE;
  }

  /**
   * Set the table name to which the given table is embedded.
   *
   * @param string $table
   *   The table name for which to set it's parents table name.
   * @param string $embedded_to_table
   *   The table name for which to set as the embedded to table.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableEmbeddedToTable($table, $embedded_to_table) {
    $this->load();
    if (empty($embedded_to_table)) {
      if (isset($this->tableInformation[$table]['embedded_to_table'])) {
        unset($this->tableInformation[$table]['embedded_to_table']);
        if (empty($this->toArray($this->tableInformation[$table]['embedded_to_table']))) {
          unset($this->tableInformation[$table]['embedded_to_table']);
        }
      }
    }
    else {
      $this->tableInformation[$table]['embedded_to_table'] = $embedded_to_table;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table description information.
   *
   * @param string $table
   *   The table name for which to get the description.
   *
   * @return string
   *   The description of the given table.
   */
  public function getTableDescription($table) {
    $this->load();
    if (isset($this->tableInformation[$table]['description'])) {
      return $this->toArray($this->tableInformation[$table]['description']);
    }
    return '';
  }

  /**
   * Set the table information for the description to the given spec.
   *
   * @param string $table
   *   The table name for which to set the description.
   * @param string $description
   *   The description to set.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableDescription($table, $description) {
    $this->load();
    if (empty($description)) {
      if (isset($this->tableInformation[$table]['description'])) {
        unset($this->tableInformation[$table]['description']);
        if (empty($this->toArray($this->tableInformation[$table]['description']))) {
          unset($this->tableInformation[$table]['description']);
        }
      }
    }
    else {
      $this->tableInformation[$table]['description'] = $description;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table field information for all fields.
   *
   * @param string $table
   *   The table name for which to get the fields.
   *
   * @return array
   *   The list of fields belonging to the given table.
   */
  public function getTableFields($table) {
    $this->load();
    if (is_string($table) && isset($this->tableInformation[$table]['fields'])) {
      return $this->toArray($this->tableInformation[$table]['fields']);
    }
    return [];
  }

  /**
   * Set the table information for all fields to the given spec.
   *
   * @param string $table
   *   The table name for which to set the fields data.
   * @param array $spec
   *   The list of fields.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableFields($table, $spec) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table]['fields'])) {
        unset($this->tableInformation[$table]['fields']);
        if (empty($this->toArray($this->tableInformation[$table]['fields']))) {
          unset($this->tableInformation[$table]['fields']);
        }
      }
    }
    else {
      $this->tableInformation[$table]['fields'] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table field information for given field.
   *
   * @param string $table
   *   The table name for which to get the field data.
   * @param string $field
   *   The field for which to fetch the data.
   *
   * @return array
   *   The field's data.
   */
  public function getTableField($table, $field) {
    $this->load();
    if (isset($this->tableInformation[$table]['fields'][$field])) {
      return $this->toArray($this->tableInformation[$table]['fields'][$field]);
    }
    return [];
  }

  /**
   * Set the table information for the field to the given spec.
   *
   * @param string $table
   *   The table name for which to set the field data.
   * @param string $field
   *   The field for which to set the data.
   * @param array $spec
   *   The field specification.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableField($table, $field, $spec) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table]['fields'][$field])) {
        unset($this->tableInformation[$table]['fields'][$field]);
        if (empty($this->toArray($this->tableInformation[$table]['fields']))) {
          unset($this->tableInformation[$table]['fields']);
        }
      }
    }
    else {
      $this->tableInformation[$table]['fields'][$field] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table primary key information.
   *
   * @param string $table
   *   The table name for which to get the primary key.
   *
   * @return array
   *   The primary key data of the given table.
   */
  public function getTablePrimaryKey($table) {
    $this->load();
    if (isset($this->tableInformation[$table]['primary key'])) {
      return $this->toArray($this->tableInformation[$table]['primary key']);
    }
    return [];
  }

  /**
   * Set the table information for the description to the given spec.
   *
   * @param string $table
   *   The table name for which to set the primary key.
   * @param array $spec
   *   The primary key specification.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTablePrimaryKey(string $table, array $spec = []) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table]['primary key'])) {
        unset($this->tableInformation[$table]['primary key']);
      }
    }
    else {
      $this->tableInformation[$table]['primary key'] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table unique key information for all unique keys.
   *
   * @param string $table
   *   The table name for which to get the unique keys.
   *
   * @return array
   *   The unique keys data of the given table.
   */
  public function getTableUniqueKeys($table) {
    $this->load();
    if (isset($this->tableInformation[$table]['unique keys'])) {
      return $this->toArray($this->tableInformation[$table]['unique keys']);
    }
    return [];
  }

  /**
   * Set the table information for all unique keys to the given spec.
   *
   * @param string $table
   *   The table name for which to set the unique keys.
   * @param array $spec
   *   The unique keys specification.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableUniqueKeys($table, $spec) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table]['unique keys'])) {
        unset($this->tableInformation[$table]['unique keys']);
      }
    }
    else {
      $this->tableInformation[$table]['unique keys'] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table unique key information for given unique key.
   *
   * @param string $table
   *   The table name for which to get the unique key.
   * @param string $unique_key
   *   The unique key name for which to get the data.
   *
   * @return array
   *   The unique key data of the given table.
   */
  public function getTableUniqueKey($table, $unique_key) {
    $this->load();
    if (isset($this->tableInformation[$table]['unique keys'][$unique_key])) {
      return $this->toArray($this->tableInformation[$table]['unique keys'][$unique_key]);
    }
    return [];
  }

  /**
   * Set the table information for the unique key to the given spec.
   *
   * @param string $table
   *   The table name for which to set the unique key.
   * @param string $unique_key
   *   The unique key name for which to set the data.
   * @param array $spec
   *   (optional) The unique key specification.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableUniqueKey(string $table, string $unique_key, array $spec = []) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table]['unique keys'][$unique_key])) {
        unset($this->tableInformation[$table]['unique keys'][$unique_key]);
        if (empty($this->toArray($this->tableInformation[$table]['unique keys']))) {
          unset($this->tableInformation[$table]['unique keys']);
        }
      }
    }
    else {
      $this->tableInformation[$table]['unique keys'][$unique_key] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table index information for all indexes.
   *
   * @param string $table
   *   The table name for which to get the index.
   *
   * @return array
   *   The indexes data of the given table.
   */
  public function getTableIndexes($table) {
    $this->load();
    if (isset($this->tableInformation[$table]['indexes'])) {
      return $this->toArray($this->tableInformation[$table]['indexes']);
    }
    return [];
  }

  /**
   * Set the table information for all indexes to the given spec.
   *
   * @param string $table
   *   The table name for which to set the indexes.
   * @param array $spec
   *   The indexes specification.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableIndexes($table, $spec) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table]['indexes'])) {
        unset($this->tableInformation[$table]['indexes']);
      }
    }
    else {
      $this->tableInformation[$table]['indexes'] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the table index information for given index.
   *
   * @param string $table
   *   The table name for which to get the index.
   * @param string $name
   *   The index name for which to get the data.
   *
   * @return array
   *   The index data of the given table.
   */
  public function getTableIndex($table, $name) {
    $this->load();
    if (isset($this->tableInformation[$table]['indexes'][$name])) {
      return $this->toArray($this->tableInformation[$table]['indexes'][$name]);
    }
    return [];
  }

  /**
   * Set the table information for the index to the given spec.
   *
   * @param string $table
   *   The table name for which to set the index.
   * @param string $name
   *   The index name for which to set the data.
   * @param array $spec
   *   (optional) The index specification.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The called object.
   */
  public function setTableIndex(string $table, string $name, array $spec = []) {
    $this->load();
    if (empty($spec)) {
      if (isset($this->tableInformation[$table]['indexes'][$name])) {
        unset($this->tableInformation[$table]['indexes'][$name]);
        if (empty($this->toArray($this->tableInformation[$table]['indexes']))) {
          unset($this->tableInformation[$table]['indexes']);
        }
      }
    }
    else {
      $this->tableInformation[$table]['indexes'][$name] = $spec;
    }
    $this->tablesToSave($table);
    return $this;
  }

  /**
   * Get the auto increment field for the given table.
   *
   * @param string $table
   *   The table name for which to get the auto increment field.
   *
   * @return string
   *   The field name of the auto increment field.
   */
  public function getTableAutoIncrementField($table) {
    $this->load();
    $auto_increment_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && mb_strtolower($field_info_value) == 'serial') {
            $auto_increment_fields[] = $field_name;
          }
        }
      }
    }

    if (count($auto_increment_fields) == 1) {
      return current($auto_increment_fields);
    }

    return NULL;
  }

  /**
   * Get the fields with a default value for the given table.
   *
   * @param string $table
   *   The table name for which to get the default value fields.
   *
   * @return array
   *   The list of field names with a default value.
   */
  public function getTableFieldsWithDefaultValue($table) {
    $this->load();
    $default_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if ((mb_strtolower($field_info_key) == 'default')) {
            $default_fields[$field_name] = $field_info_value;
          }
        }
      }
    }
    return $default_fields;
  }

  /**
   * Get the fields with a not null value for the given table.
   *
   * @param string $table
   *   The table name for which to get the not null fields.
   *
   * @return array
   *   The list of field names with the not null value set.
   */
  public function getTableFieldsWithNotNull($table) {
    $this->load();
    $not_null_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if ((mb_strtolower($field_info_key) == 'not null') && $field_info_value) {
            $not_null_fields[] = $field_name;
          }
        }
      }
    }
    return $not_null_fields;
  }

  /**
   * Get the boolean fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the boolean fields.
   *
   * @return array
   *   The list of boolean value field names.
   */
  public function getTableBooleanFields($table) {
    $this->load();
    $boolean_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && mb_strtolower($field_info_value) == 'bool') {
            $boolean_fields[] = $field_name;
          }
        }
      }
    }
    return $boolean_fields;
  }

  /**
   * Get the blob fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the blob fields.
   *
   * @return array
   *   The list of blob value field names.
   */
  public function getTableBlobFields($table) {
    $this->load();
    $blob_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && mb_strtolower($field_info_value) == 'blob') {
            $blob_fields[] = $field_name;
          }
        }
      }
    }
    return $blob_fields;
  }

  /**
   * Get the integer fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the integer fields.
   *
   * @return array
   *   The list of integer value field names.
   */
  public function getTableIntegerFields($table) {
    $this->load();
    $integer_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && (mb_strtolower($field_info_value) == 'int' || mb_strtolower($field_info_value) == 'serial')) {
            $integer_fields[] = $field_name;
          }
        }
      }
    }
    return $integer_fields;
  }

  /**
   * Get the big integer/long fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the big integer fields.
   *
   * @return array
   *   The list of big integer value field names.
   */
  public function getTableLongFields($table) {
    $this->load();
    $long_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        if (isset($field_info['type']) && isset($field_info['size']) && (mb_strtolower($field_info['type']) == 'int') && (mb_strtolower($field_info['size']) == 'big')) {
          $long_fields[] = $field_name;
        }
      }
    }
    return $long_fields;
  }

  /**
   * Get the float fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the float fields.
   *
   * @return array
   *   The list of float value field names.
   */
  public function getTableFloatFields($table) {
    $this->load();
    $float_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && mb_strtolower($field_info_value) == 'float') {
            $float_fields[] = $field_name;
          }
        }
      }
    }
    return $float_fields;
  }

  /**
   * Get the numeric fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the numeric fields.
   *
   * @return array
   *   The list of numeric value field names.
   */
  public function getTableNumericFields($table) {
    $this->load();
    $numeric_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && mb_strtolower($field_info_value) == 'numeric') {
            $numeric_fields[] = $field_name;
          }
        }
      }
    }
    return $numeric_fields;
  }

  /**
   * Get the string fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the string fields.
   *
   * @return array
   *   The list of string value field names.
   */
  public function getTableStringFields($table) {
    $this->load();
    $string_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && in_array(mb_strtolower($field_info_value), ['varchar_ascii', 'varchar', 'char', 'text'], TRUE)) {
            $string_fields[] = $field_name;
          }
        }
      }
    }
    return $string_fields;
  }

  /**
   * Get the date fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the date fields.
   *
   * @return array
   *   The list of date value field names.
   */
  public function getTableDateFields($table) {
    $this->load();
    $date_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'type' && mb_strtolower($field_info_value) == 'date') {
            $date_fields[] = $field_name;
          }
        }
      }
    }
    return $date_fields;
  }

  /**
   * Get the serialized fields for the given table.
   *
   * @param string $table
   *   The table name for which to get the serialized fields.
   *
   * @return array
   *   The list of serialized field names.
   */
  public function getTableSerializeFields($table) {
    $this->load();
    $serialize_fields = [];
    if ($this->getTableFields($table)) {
      foreach ($this->getTableFields($table) as $field_name => $field_info) {
        foreach ($field_info as $field_info_key => $field_info_value) {
          if (mb_strtolower($field_info_key) == 'serialize' && $field_info_value) {
            $serialize_fields[] = $field_name;
          }
        }
      }
    }
    return $serialize_fields;
  }

  /**
   * Get the embedded tables for the given table.
   *
   * @param string $table
   *   The table name for which to get the embedded tables.
   *
   * @return array
   *   The list of embedded tables.
   */
  public function getTableEmbeddedTables($table) {
    $this->load();
    $embedded_tables = [];
    foreach ($this->tableInformation as $name => $data) {
      if (isset($data['embedded_to_table']) && ($data['embedded_to_table'] == $table) && ($table != $name)) {
        $embedded_tables[] = $name;
      }
    }
    return $embedded_tables;
  }

  /**
   * Get the embedded full path for the given table.
   *
   * @param string $table
   *   The table name for which to get the full path.
   *
   * @return string
   *   The full path for the given table name.
   */
  public function getTableEmbeddedFullPath($table) {
    $this->load();
    $full_path = '';
    if (isset($this->tableInformation[$table]['embedded_to_table'])) {
      $embedded_to_table = $this->toArray($this->tableInformation[$table]['embedded_to_table']);
      $full_path .= $this->getTableEmbeddedFullPath($embedded_to_table) . $table . '.';
    }
    return $full_path;
  }

  /**
   * Get the base table for the given table.
   *
   * @param string $table
   *   The table name for which to get the base table.
   *
   * @return string
   *   The base table to which the given table belongs.
   */
  public function getTableBaseTable($table) {
    $this->load();
    if (isset($this->tableInformation[$table]['embedded_to_table'])) {
      $embedded_to_table = $this->toArray($this->tableInformation[$table]['embedded_to_table']);
      return $this->getTableBaseTable($embedded_to_table);
    }
    return $table;
  }

  /**
   * Get list of all base tables.
   *
   * @return array
   *   The list of all base tables in the Drupal database.
   */
  public function getAllBaseTables() {
    $this->load();
    $base_tables = [];
    foreach ($this->tableInformation as $name => $data) {
      if (!isset($data['embedded_to_table'])) {
        $base_tables[] = $name;
      }
    }
    return $base_tables;
  }

  /**
   * Save the table information to the database.
   *
   * @return bool
   *   The boolean value indicating if the database save was successful.
   */
  public function save($reload = FALSE) {
    $prefixed_table_information_table = $this->connection->getPrefix() . static::TABLE_NAME;

    $set = [];
    $unset = [];
    $this->tablesToSave = array_unique($this->tablesToSave);
    foreach ($this->tablesToSave as $table_to_save) {
      if (isset($this->tableInformation[$table_to_save])) {
        $set[$table_to_save] = $this->toArray($this->tableInformation[$table_to_save]);
      }
      else {
        $unset[$table_to_save] = '';
      }
    }
    if (!empty($set) || !empty($unset)) {
      $session = $this->connection->getMongodbSession();
      if (!empty($set) && !empty($unset)) {
        $result = $this->connection->getConnection()->selectCollection($prefixed_table_information_table)->findOneAndUpdate(
          ['_id' => $this->id],
          ['$unset' => $unset, '$set' => $set],
          [
            'upsert' => TRUE,
            'session' => $session,
          ],
        );
      }
      elseif (!empty($set)) {
        $result = $this->connection->getConnection()->selectCollection($prefixed_table_information_table)->findOneAndUpdate(
          ['_id' => $this->id],
          ['$set' => $set],
          [
            'upsert' => TRUE,
            'session' => $session,
          ],
        );
      }
      else {
        $result = $this->connection->getConnection()->selectCollection($prefixed_table_information_table)->findOneAndUpdate(
          ['_id' => $this->id],
          [
            '$unset' => $unset,
          ],
          [
            'upsert' => TRUE,
            'session' => $session,
          ],
        );
      }

      // Reset the cached table information.
      $this->tableInformation = [];

      // Reset the tables to save list.
      $this->tablesToSave = [];

      // Reload from the database.
      if ($reload) {
        $this->load();
      }

      if ($result && isset($result->_id)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Helper function for the list of tables that need to be saved.
   *
   * @param string $table
   *   The table name to save to the database.
   */
  protected function tablesToSave($table): void {
    $this->tablesToSave[] = $table;
  }

}
