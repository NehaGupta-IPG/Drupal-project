<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementPrefetchIterator;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Cursor;

// cspell:ignore typemap

/**
 * The MongoDB implementation of the StatementInterface.
 */
class Statement extends StatementPrefetchIterator {

  /**
   * The current row.
   *
   * @var array
   */
  protected $currentRow = NULL;

  /**
   * The key of the current row.
   *
   * @var int
   */
  protected $currentKey = NULL;

  /**
   * The MongoDB typemap.
   *
   * @var array
   */
  protected $typeMap = [
    'array' => 'array',
    'document' => 'array',
    'root' => 'array',
  ];

  /**
   * The MongoDB table information service.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   */
  protected $tableInformation;

  /**
   * Constructs a StatementPrefetchIterator object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \MongoDB\Driver\Cursor $cursor
   *   The cursor to be used in this class.
   * @param array $fields
   *   The fields of the query.
   * @param array $driverOptions
   *   Driver-specific options.
   * @param bool $rowCountEnabled
   *   (optional) Enables counting the rows matched. Defaults to FALSE.
   */
  public function __construct(
    protected readonly Connection $connection,
    protected Cursor $cursor,
    protected array $fields,
    protected array $driverOptions = [],
    protected readonly bool $rowCountEnabled = FALSE,
  ) {
    $this->cursor->setTypeMap($this->typeMap);
    $this->data = $cursor->toArray();
  }

  /**
   * Return the data.
   *
   * @return array
   *   The array with the data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($args = [], $options = []) {
    foreach ($this->data as &$data) {
      if (isset($data['_id']) && $data['_id'] instanceof ObjectID) {
        // If set then change _id from \MongoDB\BSON\ObjectID to its string
        // version.
        $data['_id'] = $data['_id']->__toString();
      }
      elseif (isset($data['_id']) && is_array($data['_id'])) {
        // If set by aggregate method then move data from _id to the main data
        // set where drupal is expecting it to be.
        $data += $data['_id'];
        unset($data['_id']);
      }

      foreach ($this->fields as $field) {
        $field_parts = explode('.', $field);
        if (count($field_parts) == 2) {
          $embedded_table = $field_parts[0];
          $embedded_field = $field_parts[1];
          if (!empty($data[$embedded_table]) && is_array($data[$embedded_table])) {
            $data[$field] = NULL;
            foreach ($data[$embedded_table] as $embedded_table_data) {
              if (isset($embedded_table_data[$embedded_field])) {
                if (!empty($data[$field]) && !is_array($data[$field])) {
                  $data[$field] = [$data[$field], $embedded_table_data[$embedded_field]];
                }
                elseif (!empty($data[$field]) && is_array($data[$field])) {
                  $data[$field] = array_merge($data[$field], [$embedded_table_data[$embedded_field]]);
                }
                else {
                  $data[$field] = $embedded_table_data[$embedded_field];
                }
              }
            }
          }
        }

        // If a field is not set, then added it with the value NULL.
        if (!isset($data[$field])) {
          $data[$field] = NULL;
        }

        // If a field is of the type blob then Binary->getData() to get the
        // value.
        if ($data[$field] instanceof Binary) {
          $data[$field] = $data[$field]->getData();
        }

        // SQL queries return strings instead of integers or floats.
        if (is_int($data[$field]) || is_float($data[$field])) {
          $data[$field] = (string) $data[$field];
        }

        // If a field is of the type decimal then Decimal128->__toString() to
        // get the value.
        if ($data[$field] instanceof Decimal128) {
          $data[$field] = $data[$field]->__toString();
        }

        // If a field is of the type date then UTCDateTime->__toString() to get
        // the value in milliseconds. Drupal expects seconds not milliseconds.
        // We need to divide by 1000 to make Drupal happy.
        if ($data[$field] instanceof UTCDateTime) {
          $data[$field] = (int) $data[$field]->__toString();
          $data[$field] = $data[$field] / 1000;
          $data[$field] = (string) $data[$field];
        }

        // The values of every embedded table must also be converted.
        if (is_array($data[$field]) && $this->getTableInformation()->getTable($field)) {
          // The name of the embedded table is "$field". Renamed to
          // "$embedded_table_name" for readability.
          $embedded_table_name = $field;

          // Transform the embedded table data.
          $data[$embedded_table_name] = $this->embeddedTableData($embedded_table_name, $data[$embedded_table_name]);
        }
      }
    }

    // Randomize the order of the result set.
    if (!empty($options['random_order']) && !empty($this->data)) {
      shuffle($this->data);
    }

    if (!empty($this->data)) {
      $this->currentKey = 0;
      $this->currentRow = $this->data[$this->currentKey];
      $this->rowCount = count($this->data);
      $this->markResultsetIterable(TRUE);
    }

    if (!empty($options['fetch'])) {
      if (is_string($options['fetch'])) {
        // \PDO::FETCH_PROPS_LATE tells __construct() to run before properties
        // are added to the object.
        $this->setFetchMode(\PDO::FETCH_CLASS, $options['fetch']);
      }
      else {
        $this->setFetchMode($options['fetch']);
      }
    }

    if (!empty($this->fields)) {
      $this->columnNames = $this->fields;
    }
    elseif ($this->rowCount) {
      $this->columnNames = array_keys($this->data[0]);
    }
    else {
      $this->columnNames = [];
    }

    return $this;
  }

  /**
   * Get all the field names that the query returned.
   */
  public function getFields() {
    if (isset($this->data[0])) {
      return array_keys($this->data[0]);
    }
    else {
      return [];
    }
  }

  /**
   * Method to transform the raw embedded table data to the Drupal format.
   *
   * @param string $table_name
   *   The table name belonging to the embedded table data.
   * @param array $data
   *   The embedded table data that needs to be transformed.
   *
   * @return array
   *   The transformed embedded table data.
   */
  protected function embeddedTableData($table_name, array &$data) {
    $rows = [];

    // An embedded table consist of an array of arrays. Each inner array can be
    // seen as a row of the embedded table.
    foreach ($data as $id => $row) {
      if (is_array($row)) {

        // The row of an embedded table consist of a number of key-value pairs.
        foreach ($row as $key => $value) {
          // If a field is of the type blob then Binary->getData() to get the
          // value.
          if ($value instanceof Binary) {
            $rows[$id][$key] = $value->getData();
          }
          // SQL queries return strings instead of integers or floats.
          elseif (is_int($value) || is_float($value)) {
            $rows[$id][$key] = (string) $value;
          }
          // If a field is of the type blob then Decimal128->__toString() to get
          // the value.
          elseif ($value instanceof Decimal128) {
            $rows[$id][$key] = $value->__toString();
          }
          // If a field is of the type blob then UTCDateTime->__toString() to
          // get the value.
          elseif ($value instanceof UTCDateTime) {
            $rows[$id][$key] = (int) $value->__toString();
            $rows[$id][$key] = $rows[$id][$key] / 1000;
            $rows[$id][$key] = (string) $rows[$id][$key];
          }
          // Embedded table data can have embedded tables embedded into it.
          elseif (is_array($value)) {
            $rows[$id][$key] = $this->embeddedTableData($key, $value);
          }
          else {
            $rows[$id][$key] = $value;
          }
        }

        // Embedded table rows return all fields. Add the fields that where not
        // returned by the query.
        $table_fields = $this->getTableInformation()->getTableFields($table_name);
        if (is_array($table_fields)) {
          foreach ($table_fields as $field_name => $field_data) {
            if (!in_array($field_name, array_keys($row), TRUE)) {
              $rows[$id][$field_name] = $field_data['default'] ?? NULL;
            }
          }
        }
      }
    }

    return $rows;
  }

  /**
   * Helper method to get the MongoDB table information service.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The MongoDB table information service.
   */
  protected function getTableInformation() {
    if (!isset($this->tableInformation)) {
      $this->tableInformation = $this->connection->tableInformation();
    }
    return $this->tableInformation;
  }

}
