<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection;

/**
 * The MongoDB service for sequences of tables.
 */
class Sequences {

  /**
   * The table that will hold all the sequences.
   *
   * We cannot name the table "sequences". The migration tests will then fail.
   *
   * @var string
   */
  const TABLE_NAME = 'mongodb_sequences';

  /**
   * The current database connection.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\Connection
   */
  protected $connection;

  /**
   * Constructs the Sequences object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Get the next sequence Id for the given table.
   *
   * @param string $table
   *   The table name for which to get the next incremented value.
   *
   * @return int
   *   The next incremented value for the given table.
   */
  public function nextId($table) {
    // Update the sequence.
    $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->findOneAndUpdate(
      ['_id' => $table],
      ['$inc' => ['sequence' => 1]],
      ['new' => TRUE],
    );

    if ($result && isset($result->sequence)) {
      return $result->sequence;
    }
    else {
      // Create a new sequence and the sequences table if it does not exists.
      $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->insertOne([
        '_id' => $table,
        'sequence' => 1,
        'sequence2' => 0,
      ]);

      if ($result && ($result->getInsertedCount() > 0)) {
        return 1;
      }
    }
    return 1;
  }

  /**
   * Get the next entity id for the given table.
   *
   * @param string $table
   *   The table name for which to get the next entity id incremented value.
   *
   * @return int
   *   The next incremented entity id value for the given table.
   */
  public function nextEntityId($table) {
    return $this->nextId($table);
  }

  /**
   * Get the current sequence id for the given table.
   *
   * @param string $table
   *   The table name for which to get the current incremented value.
   *
   * @return int
   *   The current incremented value for the given table.
   */
  public function currentId($table): int {
    // Update the sequence.
    $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->findOne(
      ['_id' => $table],
      ['projection' => ['sequence' => 1]],
    );

    if ($result && isset($result->sequence)) {
      return $result->sequence;
    }
    else {
      // Create a new sequence and the sequences table if it does not exists.
      $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->insertOne([
        '_id' => $table,
        'sequence' => 1,
        'sequence2' => 0,
      ]);

      if ($result && ($result->getInsertedCount() > 0)) {
        return 1;
      }
    }

    return 1;
  }

  /**
   * Get the current entity id for the given table.
   *
   * @param string $table
   *   The table name for which to get the current entity id incremented value.
   *
   * @return int
   *   The current incremented entity id value for the given table.
   */
  public function currentEntityId($table) {
    return $this->currentId($table);
  }

  /**
   * Set the sequence id for the given table.
   *
   * @param string $table
   *   The table name for which to set the entity id value.
   * @param string $value
   *   The value to set for the entity id.
   */
  public function setId($table, $value) {
    // Update the sequence.
    $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->findOneAndUpdate(
      ['_id' => $table],
      ['$set' => ['sequence' => intval($value)]],
      ['new' => TRUE],
    );

    if (!$result || !isset($result->sequence)) {
      // Create a new sequence and the sequences table when it does not exist.
      $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->insertOne([
        '_id' => $table,
        'sequence' => intval($value),
        'sequence2' => 0,
      ]);
    }
  }

  /**
   * Set the current entity id for the given table.
   *
   * @param string $table
   *   The table name for which to set the entity id value.
   * @param string $value
   *   The value to set for the entity id.
   */
  public function setEntityId($table, $value) {
    $this->setId($table, $value);
  }

  /**
   * Get the next revision id for the given table.
   *
   * @param string $table
   *   The table name for which to get the next revision id incremented value.
   *
   * @return int
   *   The next incremented revision id value for the given table.
   */
  public function nextRevisionId($table): int {
    // Update the sequence.
    $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->findOneAndUpdate(
      ['_id' => $table],
      ['$inc' => ['sequence2' => 1]],
      ['new' => TRUE],
    );

    if ($result && isset($result->sequence2)) {
      return $result->sequence2;
    }
    else {
      // Create a new sequence and the sequences table if it does not exists.
      $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->insertOne([
        '_id' => $table,
        'sequence' => 0,
        'sequence2' => 1,
      ]);

      if ($result && ($result->getInsertedCount() > 0)) {
        return 1;
      }
    }

    return 1;
  }

  /**
   * Get the current revision id for the given table.
   *
   * @param string $table
   *   The table name for which to get the current revision id incremented
   *   value.
   *
   * @return int
   *   The current incremented revision id value for the given table.
   */
  public function currentRevisionId($table): int {
    // Update the sequence.
    $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->findOne(
      ['_id' => $table],
      ['projection' => ['sequence2' => 1]],
    );

    if ($result && isset($result->sequence2)) {
      return $result->sequence2;
    }
    else {
      // Create a new sequence and the sequences table if it does not exists.
      $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->insertOne([
        '_id' => $table,
        'sequence' => 0,
        'sequence2' => 1,
      ]);

      if ($result && ($result->getInsertedCount() > 0)) {
        return 1;
      }
    }

    return 1;
  }

  /**
   * Set the revision id for the given table.
   *
   * @param string $table
   *   The table name for which to set the revision id value.
   * @param string $value
   *   The value to set for the revision id.
   */
  public function setRevisionId($table, $value) {
    // Update the sequence.
    $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->findOneAndUpdate(
      ['_id' => $table],
      ['$set' => ['sequence2' => intval($value)]],
      ['new' => TRUE],
    );

    if (!$result || !isset($result->sequence2)) {
      // Create a new sequence and the sequences table if it does not exists.
      $result = $this->connection->getConnection()->selectCollection($this->getPrefixedSequencesTableName())->insertOne([
        '_id' => $table,
        'sequence' => 0,
        'sequence2' => intval($value),
      ]);
    }
  }

  /**
   * Get the prefixed sequences table name.
   *
   * @return string
   *   The prefixed table name for the sequences table.
   */
  protected function getPrefixedSequencesTableName() {
    return $this->connection->getPrefix() . static::TABLE_NAME;
  }

}
