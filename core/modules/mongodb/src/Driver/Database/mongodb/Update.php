<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\Update as QueryUpdate;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\UTCDateTime;

// cspell:ignore addtoset

/**
 * The MongoDB implementation of \Drupal\Core\Database\Query\Update.
 */
class Update extends QueryUpdate {

  use DocumentInsertTrait;

  /**
   * An array of condition for which embedded table rows must be deleted.
   *
   * @var array
   */
  protected $embeddedTableDeleteCondition = [];

  /**
   * The MongoDB table information service.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   */
  protected $tableInformation;

  /**
   * The list of blob fields.
   *
   * @var array
   */
  protected $blobFields;

  /**
   * The list of boolean fields.
   *
   * @var array
   */
  protected $booleanFields;

  /**
   * The list of integer fields.
   *
   * @var array
   */
  protected $integerFields;

  /**
   * The list of long integer fields.
   *
   * @var array
   */
  protected $longFields;

  /**
   * The list of numeric fields.
   *
   * @var array
   */
  protected $numericFields;

  /**
   * The list of date fields.
   *
   * @var array
   */
  protected $dateFields;

  /**
   * The list of string fields.
   *
   * @var array
   */
  protected $stringFields;

  /**
   * {@inheritdoc}
   */
  public function __construct(DatabaseConnection $connection, $table, array $options = []) {
    parent::__construct($connection, $table, $options);

    $this->tableInformation = $connection->tableInformation();
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    // Make sure that condition is an object of
    // \Drupal\mongodb\Driver\Database\mongodb\Condition.
    return $this->connection->condition($conjunction);
  }

  /**
   * Creates an object holding the data for an embedded table.
   *
   * @param string $action
   *   The action of how to update the embedded data. Can be "APPEND" or
   *   "REPLACE". Defaults to "REPLACE".
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\EmbeddedTableData
   *   An object holding the data for an embedded table.
   */
  public function embeddedTableData($action = 'REPLACE') {
    return new EmbeddedTableData($this->connection, $this->table, $this->tableInformation, $action);
  }

  /**
   * Build a condition for which embedded table rows will be deleted.
   *
   * @param string $embedded_table
   *   The embedded table name.
   * @param string|\Drupal\Core\Database\Query\ConditionInterface $field
   *   The name of the field to check. This can also be QueryConditionInterface
   *   in itself. Use where(), if you would like to add a more complex condition
   *   involving operators or functions, or an already compiled condition.
   * @param string|array|\Drupal\Core\Database\Query\SelectInterface|null $value
   *   The value to test the field against. In most cases, and depending on the
   *   operator, this will be a scalar or an array. As SQL accepts select
   *   queries on any place where a scalar value or set is expected, $value may
   *   also be a(n array of) SelectInterface(s). If $operator is a unary
   *   operator, e.g. IS NULL, $value will be ignored and should be null. If
   *   the operator requires a subquery, e.g. EXISTS, the $field will be ignored
   *   and $value should be a SelectInterface object.
   * @param string|null $operator
   *   The operator to use. Supported for all supported databases are at least:
   *   - The comparison operators =, <>, <, <=, >, >=.
   *   - The operators (NOT) BETWEEN, (NOT) IN, (NOT) EXISTS, (NOT) LIKE.
   *   Other operators (e.g. LIKE, BINARY) may or may not work. Defaults to =.
   *
   * @throws \Drupal\Core\Database\SchemaObjectDoesNotExistException
   *   If the specified embedded table doesn't belong to the base table.
   *
   * @return $this
   *   The called object.
   */
  public function embeddedTableDeleteCondition($embedded_table, $field, $value = NULL, $operator = '=') {
    $embedded_to_table = $this->tableInformation->getTableEmbeddedToTable($embedded_table);
    if (!$embedded_to_table || ($embedded_to_table != $this->table)) {
      throw new SchemaObjectDoesNotExistException("Cannot add fields to the embedded table $embedded_table, because the embedded table does not belong to the " . $this->table . '.');
    }

    // Make sure that condition is a object of
    // \Drupal\mongodb\Driver\Database\mongodb\Condition.
    if (!isset($this->embeddedTableDeleteCondition[$embedded_table]) || !($this->embeddedTableDeleteCondition[$embedded_table] instanceof Condition)) {
      $this->embeddedTableDeleteCondition[$embedded_table] = $this->connection->condition('AND');
    }
    $this->embeddedTableDeleteCondition[$embedded_table]->condition($field, $value, $operator);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // List of BLOB fields.
    $this->blobFields = $this->tableInformation->getTableBlobFields($this->table);
    $this->booleanFields = $this->tableInformation->getTableBooleanFields($this->table);
    $this->integerFields = $this->tableInformation->getTableIntegerFields($this->table);
    $this->longFields = $this->tableInformation->getTableLongFields($this->table);
    $this->numericFields = $this->tableInformation->getTableNumericFields($this->table);
    $this->dateFields = $this->tableInformation->getTableDateFields($this->table);
    $this->stringFields = $this->tableInformation->getTableStringFields($this->table);

    $update_mul = [];
    $update_inc = [];
    $update_set = [];
    $update_unset = [];
    $update_addtoset = [];
    $update_pull = [];

    foreach ($this->expressionFields as $field => $data) {
      if ($data['expression'] instanceof SelectInterface) {
        throw new MongodbSQLException('MongoDB does not support subqueries.');
      }

      // Replace the placeholders in the expression with the real values.
      if (!empty($data['arguments']) && is_array($data['arguments'])) {
        $data['expression'] = str_replace(array_keys($data['arguments']), array_values($data['arguments']), $data['expression']);
      }

      // Translate an expression in something that MongoDB can handle.
      preg_match_all('/[a-z0-9_]+|[0-9]+|[*+-\/]{1}/i', $data['expression'], $math_matches);
      $math_matches = reset($math_matches);
      if ((count($math_matches) == 4) && ($math_matches[2] == '-')) {
        // If the expression is of variable plus a negative number, change it to
        // variable minus the number.
        $math_matches[1] = $math_matches[2];
        $math_matches[2] = $math_matches[3];
        unset($math_matches[3]);
      }

      if (count($math_matches) == 1) {
        $value = reset($math_matches);
        if (in_array($field, $this->integerFields) || in_array($field, $this->longFields)) {
          $update_set[$field] = (int) $value;
        }
        elseif (in_array($field, $this->stringFields)) {
          $update_set[$field] = (string) $value;
        }
        elseif (in_array($field, $this->booleanFields)) {
          $update_set[$field] = (bool) $value;
        }
        else {
          $update_set[$field] = $value;
        }
      }
      elseif ((count($math_matches) == 3) && in_array($math_matches[1], ['*', '+', '-'])) {
        $left_side = NULL;
        if (ctype_digit($math_matches[0]) || is_int($math_matches[0])) {
          $left_side = (int) $math_matches[0];
        }
        elseif (is_numeric($math_matches[0])) {
          $left_side = (float) $math_matches[0];
        }

        $right_side = NULL;
        if (ctype_digit($math_matches[2]) || is_int($math_matches[2])) {
          $right_side = (int) $math_matches[2];
        }
        elseif (is_numeric($math_matches[2])) {
          $right_side = (float) $math_matches[2];
        }

        if (is_null($left_side) xor is_null($right_side)) {
          switch ($math_matches[1]) {
            case '*':
              if ($field == $math_matches[0]) {
                $update_mul[$field] = $right_side;
              }
              elseif ($field == $math_matches[2]) {
                $update_mul[$field] = $left_side;
              }
              break;

            case '+':
              if ($field == $math_matches[0]) {
                $update_inc[$field] = $right_side;
              }
              elseif ($field == $math_matches[2]) {
                $update_inc[$field] = $left_side;
              }
              break;

            case '-':
              if ($field == $math_matches[0]) {
                $update_inc[$field] = $right_side * -1;
              }
              elseif ($field == $math_matches[2]) {
                $update_inc[$field] = $left_side * -1;
              }
              break;

            default:
              throw new MongodbSQLException('MongoDB does not support the mathematical operator: "' . $math_matches[1] . '".');
          }
        }
        else {
          throw new MongodbSQLException('MongoDB does not support complex expressions1: "' . $data['expression'] . '".');
        }
      }
      else {
        throw new MongodbSQLException('MongoDB does not support complex expressions2: "' . $data['expression'] . '".');
      }
    }

    foreach ($this->fields as $field => $value) {
      if (is_null($value)) {
        $field_data = $this->tableInformation->getTableField($this->table, $field);
        if (isset($field_data['type']) && isset($field_data['not null']) && ($field_data['type'] == 'int') && (!empty($field_data['not null']))) {
          // If a field has the value NULL and the field setting not null, then
          // give the field the value zero.
          $update_set[$field] = 0;
        }
        elseif (isset($field_data['type']) && isset($field_data['not null']) && ($field_data['type'] == 'bool') && (!empty($field_data['not null']))) {
          // If a field has the value NULL and the field setting not null, then
          // give the field the value FALSE.
          $update_set[$field] = FALSE;
        }
        else {
          $update_unset[$field] = $value;
        }
      }
      elseif (in_array($field, $this->blobFields) && !($value instanceof Binary)) {
        $update_set[$field] = new Binary($value, Binary::TYPE_GENERIC);
      }
      elseif (in_array($field, $this->booleanFields)) {
        $update_set[$field] = (bool) $value;
      }
      elseif (in_array($field, $this->integerFields) || in_array($field, $this->longFields)) {
        $update_set[$field] = (int) $value;
      }
      elseif (in_array($field, $this->stringFields)) {
        $update_set[$field] = (string) $value;
      }
      elseif (in_array($field, $this->numericFields) && !($value instanceof Decimal128)) {
        $update_set[$field] = new Decimal128($value);
      }
      elseif (in_array($field, $this->dateFields) && !($value instanceof UTCDateTime)) {
        $update_set[$field] = new UTCDateTime($value * 1000);
      }
      elseif (($value instanceof EmbeddedTableData)) {
        // Get the embedded table name from the field sting.
        if (strrpos($field, '.') !== FALSE) {
          $embedded_table = substr($field, strrpos($field, '.') + 1);
        }
        else {
          $embedded_table = $field;
        }

        // Make sure that the embedded table belongs to the base table
        // "$this->table".
        if ($this->table == $this->tableInformation->getTableBaseTable($embedded_table)) {
          // The variable $field holds the embedded table name.
          // The variable $value holds an instance of
          // \Drupal\mongodb\Driver\Database\mongodb\EmbeddedTableData.
          $embedded_table_rows = $value->compile($embedded_table);
          foreach ($embedded_table_rows as &$embedded_table_row) {
            $embedded_table_row = $this->getUpdateValuesForTable($embedded_table, $embedded_table_row);
          }

          if ($value->deleteExistingDataOnUpdate()) {
            $update_set[$field] = $embedded_table_rows;
          }
          else {
            // Maybe it is better to use the MongoDB $push operator for this.
            $update_addtoset[$field] = ['$each' => $embedded_table_rows];
          }
        }
      }
      else {
        $update_set[$field] = $value;
      }
    }

    // Add the delete conditions for the embedded tables.
    foreach ($this->embeddedTableDeleteCondition as $embedded_table_name => &$embeddedTableDeleteCondition) {
      if (($embeddedTableDeleteCondition instanceof Condition) && count($embeddedTableDeleteCondition)) {
        $embeddedTableDeleteCondition->compile($this->connection, $this);
        $update_pull[$embedded_table_name] = $embeddedTableDeleteCondition->toMongoArray();
      }
    }

    $update_values = [];
    if (!empty($update_mul)) {
      $update_values['$mul'] = $update_mul;
    }
    if (!empty($update_inc)) {
      $update_values['$inc'] = $update_inc;
    }
    if (!empty($update_set)) {
      $update_values['$set'] = $update_set;
    }
    if (!empty($update_unset)) {
      $update_values['$unset'] = $update_unset;
    }
    if (!empty($update_addtoset)) {
      $update_values['$addToSet'] = $update_addtoset;
    }
    if (!empty($update_pull)) {
      $update_values['$pull'] = $update_pull;
    }

    // Compile the condition.
    if (count($this->condition)) {
      $this->condition->compile($this->connection, $this);
    }

    $prefixed_table = $this->connection->getPrefix() . $this->table;

    try {
      if (!empty($update_values)) {
        $result = $this->connection->getConnection()->selectCollection($prefixed_table)->updateMany(
          $this->condition->toMongoArray(),
          $update_values,
          [
            'session' => $this->connection->getMongodbSession(),
          ],
        );
        if ($result && $result->isAcknowledged()) {
          // Return the number of rows matched by query.
          return $result->getMatchedCount();
        }
      }
    }
    catch (\Exception $e) {
      $this->connection->exceptionHandler()->handleExecutionException($e, NULL, $update_values, $this->queryOptions);
    }
  }

  /**
   * Update the values for the table.
   *
   * @param string $table
   *   The table name.
   * @param array $fields
   *   The fields to be updated.
   *
   * @return mixed
   *   The updated fields.
   */
  protected function getUpdateValuesForTable($table, $fields) {
    $blob_fields = $this->tableInformation->getTableBlobFields($table);
    $boolean_fields = $this->tableInformation->getTableBooleanFields($table);
    $integer_fields = $this->tableInformation->getTableIntegerFields($table);
    $long_fields = $this->tableInformation->getTableLongFields($table);
    $numeric_fields = $this->tableInformation->getTableNumericFields($table);
    $date_fields = $this->tableInformation->getTableDateFields($table);
    $string_fields = $this->tableInformation->getTableStringFields($table);

    foreach ($fields as $field => &$value) {
      if (is_null($value)) {
        $field_data = $this->tableInformation->getTableField($table, $field);
        if (($field_data['type'] == 'int') && (!empty($field_data['not null']))) {
          // If a field has the value NULL and the field setting not null, then
          // give the field the value zero.
          $value = 0;
        }
        elseif (($field_data['type'] == 'bool') && (!empty($field_data['not null']))) {
          // If a field has the value NULL and the field setting not null, then
          // give the field the value FALSE.
          $value = FALSE;
        }
        else {
          $value = $value;
        }
      }
      elseif (in_array($field, $blob_fields) && !($value instanceof Binary)) {
        $value = new Binary($value, Binary::TYPE_GENERIC);
      }
      elseif (in_array($field, $boolean_fields)) {
        $value = (bool) $value;
      }
      elseif (in_array($field, $integer_fields) || in_array($field, $long_fields)) {
        $value = (int) $value;
      }
      elseif (in_array($field, $string_fields)) {
        $value = (string) $value;
      }
      elseif (in_array($field, $numeric_fields) && !($value instanceof Decimal128)) {
        $value = new Decimal128($value);
      }
      elseif (in_array($field, $date_fields) && !($value instanceof UTCDateTime)) {
        $value = new UTCDateTime($value * 1000);
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    // Nothing to do.
    return '';
  }

}
