<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\Query\FieldsOverlapException;
use Drupal\Core\Database\Query\NoFieldsException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\UTCDateTime;

/**
 * Provides common functionality for INSERT and UPSERT queries.
 */
trait DocumentInsertTrait {

  /**
   * Preprocesses and validates the data for table insertion.
   *
   * @param string $table
   *   The table name of the foreign table to be embedded in the base table.
   * @param array $insert_fields
   *   The insert fields to be used for creating the insert document.
   * @param array $default_fields
   *   The default fields to be used for creating the insert document.
   * @param array $insert_values
   *   The insert values to be used for creating the insert document.
   *
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   * @throws \Drupal\Core\Database\Query\FieldsOverlapException
   * @throws \Drupal\Core\Database\Query\NoFieldsException
   */
  protected function validateDataForTableInsert($table, $insert_fields, $default_fields, $insert_values) {
    if (!$this->tableInformation) {
      $this->tableInformation = $this->connection->tableInformation();
    }

    // Confirm that the user did not try to specify an identical field and
    // default field.
    if (array_intersect($insert_fields, $default_fields)) {
      throw new FieldsOverlapException('You may not specify the same field to have a value and a schema-default value.');
    }

    // Don't execute query without fields.
    if (count($insert_fields) + count($default_fields) == 0) {
      throw new NoFieldsException('There are no fields available to insert with.');
    }

    // Calculate all not null fields with no value set on insert.
    $unresolved_not_null_fields = $not_null_fields = $this->tableInformation->getTableFieldsWithNotNull($table);
    $default_value_fields = $this->tableInformation->getTableFieldsWithDefaultValue($table);
    $auto_increment_field = $this->tableInformation->getTableAutoIncrementField($table);
    foreach ($not_null_fields as $not_null_field) {
      // Remove all not null fields that will have a value set by the
      // $insert_values array.
      foreach ($insert_fields as $insert_field_name) {
        if ($not_null_field == $insert_field_name) {
          $unresolved_not_null_fields = array_diff($unresolved_not_null_fields, [$not_null_field]);
        }
      }

      // Remove all not null fields that will have a default value.
      foreach ($default_value_fields as $default_field_name => $default_field_value) {
        if ($not_null_field == $default_field_name && !is_null($default_field_value)) {
          $unresolved_not_null_fields = array_diff($unresolved_not_null_fields, [$not_null_field]);
        }
      }

      // Remove all not null fields that will an auto-incremented value.
      if ($not_null_field == $auto_increment_field) {
        $unresolved_not_null_fields = array_diff($unresolved_not_null_fields, [$not_null_field]);
      }
    }

    // If there are not null fields that will have no value set on insert,
    // then throw an error.
    if (!empty($unresolved_not_null_fields)) {
      throw new IntegrityConstraintViolationException('There are not null fields that have no value set.');
    }
  }

  /**
   * Helper function for updating the insert document for MongoDB storage.
   *
   * @param string $table
   *   The table name of the foreign table to be embedded in the base table.
   * @param array $insert_fields
   *   An array of fields on which to insert. This array may be indexed or
   *   associative. If indexed, the array is taken to be the list of fields.
   *   If associative, the keys of the array are taken to be the fields and
   *   the values are taken to be corresponding values to insert. If a
   *   $values argument is provided, $insert_fields must be indexed.
   * @param array $insert_values
   *   The insert values to be used for creating the insert document.
   *
   * @return array
   *   The total insert document part for the given table.
   *
   * @throws \Drupal\mongodb\Driver\Database\mongodb\MongodbSQLException
   */
  protected function getInsertDocumentForTable($table, $insert_fields, $insert_values) {
    if (!$this->tableInformation) {
      $this->tableInformation = $this->connection->tableInformation();
    }
    $insert_document = array_combine($insert_fields, $insert_values);

    // The default fields values must be added because MongoDB does not know
    // anything about default fields values.
    if ($default_value_fields = $this->tableInformation->getTableFieldsWithDefaultValue($table)) {
      $insert_document = array_merge($default_value_fields, $insert_document);
    }

    // The auto-increment fields must be added because MongoDB does not know
    // anything about auto-increment fields.
    if ($auto_increment_field = $this->tableInformation->getTableAutoIncrementField($table)) {
      // We need the sequences service that belongs to the current connection.
      $sequences = $this->connection->sequences();
      // Auto increment field can have the value of zero. Like with the
      // anonymous user.
      if (!isset($insert_document[$auto_increment_field])) {
        $insert_document[$auto_increment_field] = $sequences->nextId($table);
      }
      else {
        $current_id = $sequences->currentId($table);
        if (intval($insert_document[$auto_increment_field]) >= $current_id) {
          $sequences->setId($table, intval($insert_document[$auto_increment_field]));
        }
        elseif ($this->tableInformation->getTableBaseTable($table) === $table) {
          // The table is a base table and not an embedded table.
          if ($integer_fields = $this->tableInformation->getTableIntegerFields($table)) {
            if (in_array($auto_increment_field, $integer_fields)) {
              $insert_document[$auto_increment_field] = (int) $insert_document[$auto_increment_field];
            }
          }

          $prefixed_table = $this->connection->getPrefix() . $table;
          $result = $this->connection->getConnection()->selectCollection($prefixed_table)->findOne(
            [
              $auto_increment_field => ['$eq' => $insert_document[$auto_increment_field]],
            ],
            [
              'projection' => [
                $auto_increment_field => 1,
                '_id' => 1,
              ],
              'session' => $this->connection->getMongodbSession(),
            ],
          );

          // Get the first result.
          if (is_array($result)) {
            $result = reset($result);
          }

          // @todo Decide what is the best to do. Update the already set
          // auto-increment field or throw an exception.
          // if (!empty($result)) {
          // We can add a test here to make sure that value is not used in the
          // database, before throwing an exception.
          // throw new MongodbSQLException('You cannot give an auto-increment
          // field a lower value then its sequence value.');
          // }
        }
      }
    }

    // Change the BLOB fields value in something that MongoDB can save.
    if ($blob_fields = $this->tableInformation->getTableBlobFields($table)) {
      foreach ($blob_fields as $blob_field) {
        if (!isset($insert_document[$blob_field])) {
          $insert_document[$blob_field] = NULL;
        }
        elseif (!($insert_document[$blob_field] instanceof Binary)) {
          // The array value must exist or an undefined index exception will be
          // thrown.
          if (!isset($insert_document[$blob_field])) {
            $insert_document[$blob_field] = NULL;
          }
          $insert_document[$blob_field] = new Binary($insert_document[$blob_field], Binary::TYPE_GENERIC);
        }
      }
    }

    if ($boolean_fields = $this->tableInformation->getTableBooleanFields($table)) {
      foreach ($boolean_fields as $boolean_field) {
        // The array value must exist or an undefined index exception will be
        // thrown.
        if (!isset($insert_document[$boolean_field])) {
          $insert_document[$boolean_field] = NULL;
        }
        $insert_document[$boolean_field] = (bool) $insert_document[$boolean_field];
      }
    }

    // Change the big integer/LONG fields value in something that MongoDB can
    // save.
    if ($long_fields = $this->tableInformation->getTableLongFields($table)) {
      foreach ($long_fields as $long_field) {
        if (!isset($insert_document[$long_field]) || is_null($insert_document[$long_field])) {
          $long_field_data = $this->tableInformation->getTableField($table, $long_field);
          if (isset($long_field_data['not null']) && $long_field_data['not null']) {
            // If a field has the value NULL and the field setting not null,
            // then give the field the value zero.
            // @todo Use the $long_field_data['default'] value. Not zero!
            $insert_document[$long_field] = 0;
          }
        }
        elseif (!isset($insert_document[$long_field])) {
          // The array value must exist or an undefined index exception will be
          // thrown.
          $insert_document[$long_field] = NULL;
        }
        else {
          $insert_document[$long_field] = (int) $insert_document[$long_field];
        }
      }
    }

    // Integer fields must be of the type integer or the table validation
    // will fail.
    if ($integer_fields = $this->tableInformation->getTableIntegerFields($table)) {
      foreach ($integer_fields as $integer_field) {
        if (!isset($insert_document[$integer_field]) || is_null($insert_document[$integer_field])) {
          $integer_field_data = $this->tableInformation->getTableField($table, $integer_field);
          if (isset($integer_field_data['not null']) && $integer_field_data['not null']) {
            // If a field has the value NULL and the field setting not null,
            // then give the field the value zero.
            // @todo Use the $integer_field_data['default'] value. Not zero!
            $insert_document[$integer_field] = 0;
          }
          else {
            // The array value must exist or an undefined index exception will
            // be thrown.
            $insert_document[$integer_field] = NULL;
          }
        }

        if (isset($insert_document[$integer_field]) && !is_null($insert_document[$integer_field])) {
          $negative = FALSE;
          if (is_string($insert_document[$integer_field]) && substr($insert_document[$integer_field], 0, 1) == '-') {
            // Remove the negative sign from the start of the string.
            $insert_document[$integer_field] = substr($insert_document[$integer_field], 1);
            $negative = TRUE;
          }
          if (is_int($insert_document[$integer_field]) || ctype_digit($insert_document[$integer_field])) {
            // Only change not null strings with an integer value to an
            // integer.
            $insert_document[$integer_field] = (int) $insert_document[$integer_field];
          }
          if ($negative) {
            $insert_document[$integer_field] = -1 * $insert_document[$integer_field];
          }
        }
      }
    }

    // Float fields must be of the type float or the table validation will fail.
    if ($float_fields = $this->tableInformation->getTableFloatFields($table)) {
      foreach ($float_fields as $float_field) {
        if (!isset($insert_document[$float_field])) {
          $insert_document[$float_field] = NULL;
        }
        elseif (!is_null($insert_document[$float_field]) && is_numeric($insert_document[$float_field])) {
          // Only change not null strings with an float value to a float.
          $insert_document[$float_field] = (float) $insert_document[$float_field];
        }
      }
    }

    // Numeric fields must be of the type decimal or the table validation will
    // fail.
    if ($numeric_fields = $this->tableInformation->getTableNumericFields($table)) {
      foreach ($numeric_fields as $numeric_field) {
        if (!isset($insert_document[$numeric_field])) {
          $insert_document[$numeric_field] = NULL;
        }
        elseif (!($insert_document[$numeric_field] instanceof Decimal128)) {
          $numeric_field_data = $this->tableInformation->getTableField($table, $numeric_field);
          $precision = isset($numeric_field_data['precision']) ? intval($numeric_field_data['precision']) : 0;
          $scale = isset($numeric_field_data['scale']) ? intval($numeric_field_data['scale']) : 0;
          $divisor = pow(10, ($precision - $scale));
          $insert_document[$numeric_field] = fmod((float) $insert_document[$numeric_field], $divisor);
          $insert_document[$numeric_field] = number_format($insert_document[$numeric_field], $scale, '.', '');
          $insert_document[$numeric_field] = new Decimal128($insert_document[$numeric_field]);
        }
      }
    }

    // String fields must be of the type string or the table validation will
    // fail.
    if ($string_fields = $this->tableInformation->getTableStringFields($table)) {
      foreach ($string_fields as $string_field) {
        $string_field_data = $this->tableInformation->getTableField($table, $string_field);

        if (isset($insert_document[$string_field])) {
          $insert_document[$string_field] = (string) $insert_document[$string_field];
        }
        elseif (!empty($string_field_data['not null'])) {
          // If a field has the value NULL and the field setting not null,
          // then give the field the value of an empty string.
          $insert_document[$string_field] = '';
        }

        if (isset($string_field_data['length']) && isset($insert_document[$string_field])) {
          $max_length = intval($string_field_data['length']);
          // strlen() returns the number of bytes rather than the number of
          // characters in a string.
          $string_field_length = mb_strlen($insert_document[$string_field]);

          if (($max_length > 0) && ($string_field_length > $max_length)) {
            $insert_document[$string_field] = substr($insert_document[$string_field], 0, intval($string_field_data['length']));
          }
        }
      }
    }

    // Date fields must be of the type date or the table validation will fail.
    if ($date_fields = $this->tableInformation->getTableDateFields($table)) {
      foreach ($date_fields as $date_field) {
        if (!isset($insert_document[$date_field])) {
          $insert_document[$date_field] = NULL;
        }
        elseif (!($insert_document[$date_field] instanceof UTCDateTime)) {
          // The constructor for UTCDateTime wants the time in milliseconds
          // since the Unix epoch (Jan 1, 1970). Drupal works with the number
          // of seconds since the Unix epoch (Jan 1, 1970). We need to multiply
          // the value with 1000.
          $insert_document[$date_field] = new UTCDateTime($insert_document[$date_field] * 1000);
        }
      }
    }

    // Embedded table data needs to be compiled for saving in MongoDB.
    $embedded_tables = $this->tableInformation->getTableEmbeddedTables($table);
    foreach ($embedded_tables as $embedded_table) {
      if (!empty($insert_document[$embedded_table]) && ($insert_document[$embedded_table] instanceof EmbeddedTableData)) {
        $insert_document[$embedded_table] = ($insert_document[$embedded_table])->compile($embedded_table);
      }
    }

    // Do not insert into MongoDB field that have the value NULL. A
    // non-existing field has by default the value NULL attached to it.
    foreach ($insert_document as $field => $value) {
      if (is_null($value)) {
        unset($insert_document[$field]);
      }
    }

    return $insert_document;
  }

}
