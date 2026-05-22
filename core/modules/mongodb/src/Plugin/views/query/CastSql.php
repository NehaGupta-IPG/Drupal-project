<?php

namespace Drupal\mongodb\Plugin\views\query;

use Drupal\Core\Database\Connection;
use Drupal\views\Plugin\views\query\CastSqlInterface;

/**
 * Cast handling in MongoDB.
 */
class CastSql implements CastSqlInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs the MySQL-specific date sql class.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldAsInt(string $field): string {
    $field = '$' . $this->database->escapeField($field);
    // Serialize the field, so that it is a string. The method
    // Condition::compare() only accepts string values as parameters.
    return serialize(['$toInt' => $field]);
  }

}
