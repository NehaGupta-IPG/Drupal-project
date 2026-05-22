<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Query\Merge as QueryMerge;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\UTCDateTime;

/**
 * MongoDB implementation of \Drupal\Core\Database\Query\Merge.
 */
class Merge extends QueryMerge {

  /**
   * The MongoDB table information service.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   */
  protected $tableInformation;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, $table, array $options = []) {
    parent::__construct($connection, $table, $options);

    // We need the table information service that belongs to the current
    // connection.
    $this->tableInformation = $connection->tableInformation();
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    // Make sure that condition is a object of \Drupal\mongodb\Driver\Condition.
    return $this->connection->condition($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function expression($field, $expression, ?array $arguments = NULL) {
    throw new MongodbSQLException('MongoDB does not support expressions in Merge queries.');
  }

  /**
   * {@inheritdoc}
   */
  public function keys(array $fields, array $values = []) {
    if ($values) {
      $fields = array_combine($fields, $values);
    }
    foreach ($fields as $key => $value) {
      // For MongoDB it is very important that the value part of a condition is
      // of the correct type.
      $field_data = $this->tableInformation->getTableField($this->table, $key);
      // Only cast the value when the value is not NULL.
      if (isset($field_data['type']) && ($value !== NULL)) {
        switch (mb_strtolower($field_data['type'])) {
          case 'blob':
            if (!($value instanceof Binary)) {
              $value = new Binary($value, Binary::TYPE_GENERIC);
            }
            break;

          case 'bool':
            $value = (bool) $value;
            break;

          case 'int':
          case 'serial':
            $value = (int) $value;
            break;

          case 'float':
            $value = (float) $value;
            break;

          case 'numeric':
            if (!($value instanceof Decimal128)) {
              $precision = isset($field_data['precision']) ? intval($field_data['precision']) : 0;
              $scale = isset($field_data['scale']) ? intval($field_data['scale']) : 0;
              $divisor = pow(10, ($precision - $scale));
              $value = fmod((float) $value, $divisor);
              $value = number_format($value, $scale, '.', '');
              $value = new Decimal128($value);
            }
            break;

          case 'varchar_ascii':
          case 'varchar':
          case 'char':
          case 'text':
            $value = (string) $value;
            break;

          case 'date':
            if (!($value instanceof UTCDateTime)) {
              $value = new UTCDateTime($value * 1000);
            }
            break;

        }
      }

      $this->insertFields[$key] = $value;
      $this->condition($key, $value);
    }
    return $this;
  }

}
