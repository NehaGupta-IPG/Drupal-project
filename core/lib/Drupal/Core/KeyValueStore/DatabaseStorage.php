<?php

namespace Drupal\Core\KeyValueStore;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Database\Query\Merge;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\mongodb\Driver\Database\mongodb\Statement;

/**
 * Defines a default key/value store implementation.
 *
 * This is Drupal's default key/value store implementation. It uses the database
 * to store key/value data.
 */
class DatabaseStorage extends StorageBase {

  use DependencySerializationTrait;

  /**
   * The serialization class to use.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The name of the SQL table to use.
   *
   * @var string
   */
  protected $table;

  /**
   * Indicator for the existence of the database table.
   *
   *  This variable is only used by the database driver for MongoDB.
   *
   * @var bool
   */
  protected $tableExists = FALSE;

  /**
   * Overrides Drupal\Core\KeyValueStore\StorageBase::__construct().
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   The serialization class to use.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The name of the SQL table to use, defaults to key_value.
   */
  public function __construct($collection, SerializationInterface $serializer, Connection $connection, $table = 'key_value') {
    parent::__construct($collection);
    $this->serializer = $serializer;
    $this->connection = $connection;
    $this->table = $table;
  }

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    if ($this->connection->driver() == 'mongodb') {
      $prefixed_table = $this->connection->getPrefix() . $this->table;
      $cursor = $this->connection->getConnection()->selectCollection($prefixed_table)->find(
        ['collection' => ['$eq' => (string) $this->collection], 'name' => ['$eq' => (string) $key]],
        [
          'projection' => ['_id' => 1],
          'session' => $this->connection->getMongodbSession(),
        ]
      );

      if ($cursor && !empty($cursor->toArray())) {
        return TRUE;
      }
      return FALSE;
    }
    else {
      try {
        return (bool) $this->connection->query('SELECT 1 FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection AND [name] = :key', [
          ':collection' => $this->collection,
          ':key' => $key,
        ])->fetchField();
      }
      catch (\Exception $e) {
        $this->catchException($e);
        return FALSE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys) {
    $values = [];
    try {
      if ($this->connection->driver() == 'mongodb') {
        if (empty($keys)) {
          return [];
        }

        // Check that key values are string values.
        assert(Inspector::assertAllStrings($keys), 'All keys must be strings.');

        $prefixed_table = $this->connection->getPrefix() . $this->table;
        $cursor = $this->connection->getConnection()->selectCollection($prefixed_table)->find(
          [
            'collection' => ['$eq' => (string) $this->collection],
            'name' => ['$in' => $keys],
          ],
          [
            'projection' => ['name' => 1, 'value' => 1, '_id' => 0],
            'session' => $this->connection->getMongodbSession(),
          ],
        );

        $statement = new Statement($this->connection, $cursor, ['name', 'value']);
        $result = $statement->execute()->fetchAllAssoc('name');

      }
      else {
        $result = $this->connection->query('SELECT [name], [value] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [name] IN ( :keys[] ) AND [collection] = :collection', [':keys[]' => $keys, ':collection' => $this->collection])->fetchAllAssoc('name');
      }
      foreach ($keys as $key) {
        if (isset($result[$key])) {
          $values[$key] = $this->serializer->decode($result[$key]->value);
        }
      }
    }
    catch (\Exception) {
      // @todo Perhaps if the database is never going to be available,
      // key/value requests should return FALSE in order to allow exception
      // handling to occur but for now, keep it an array, always.
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    try {
      if ($this->connection->driver() == 'mongodb') {
        $prefixed_table = $this->connection->getPrefix() . $this->table;
        $cursor = $this->connection->getConnection()->selectCollection($prefixed_table)->find(
          ['collection' => ['$eq' => (string) $this->collection]],
          [
            'projection' => ['name' => 1, 'value' => 1, '_id' => 0],
            'session' => $this->connection->getMongodbSession(),
          ]
        );

        $statement = new Statement($this->connection, $cursor, ['name', 'value']);
        $result = $statement->execute();
      }
      else {
        $result = $this->connection->query('SELECT [name], [value] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection', [':collection' => $this->collection]);
      }
    }
    catch (\Exception $e) {
      $this->catchException($e);
      $result = [];
    }

    $values = [];
    foreach ($result as $item) {
      if ($item) {
        $values[$item->name] = $this->serializer->decode($item->value);
      }
    }
    return $values;
  }

  /**
   * Saves a value for a given key.
   *
   * This will be called by set() within a try block.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   */
  protected function doSet($key, $value) {
    $this->connection->merge($this->table)
      ->keys([
        'name' => $key,
        'collection' => $this->collection,
      ])
      ->fields(['value' => $this->serializer->encode($value)])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    if (($this->connection->driver() == 'mongodb') && !$this->tableExists) {
      $this->tableExists = $this->ensureTableExists();
    }

    try {
      $this->doSet($key, $value);
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the table.
      if ($this->ensureTableExists()) {
        $this->doSet($key, $value);
      }
      else {
        throw $e;
      }
    }
  }

  /**
   * Saves a value for a given key if it does not exist yet.
   *
   * This will be called by setIfNotExists() within a try block.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   *
   * @return bool
   *   TRUE if the data was set, FALSE if it already existed.
   */
  public function doSetIfNotExists($key, $value) {
    if (($this->connection->driver() == 'mongodb') && !$this->tableExists) {
      $this->tableExists = $this->ensureTableExists();
    }

    $result = $this->connection->merge($this->table)
      ->insertFields([
        'collection' => $this->collection,
        'name' => $key,
        'value' => $this->serializer->encode($value),
      ])
      ->condition('collection', $this->collection)
      ->condition('name', (string) $key)
      ->execute();
    return $result == Merge::STATUS_INSERT;
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($key, $value) {
    try {
      return $this->doSetIfNotExists($key, $value);
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the table.
      if ($this->ensureTableExists()) {
        return $this->doSetIfNotExists($key, $value);
      }
      else {
        throw $e;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rename($key, $new_key) {
    if (($this->connection->driver() == 'mongodb') && !$this->tableExists) {
      $this->tableExists = $this->ensureTableExists();
    }

    try {
      $this->connection->update($this->table)
        ->fields(['name' => $new_key])
        ->condition('collection', $this->collection)
        ->condition('name', (string) $key)
        ->execute();
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys) {
    if (($this->connection->driver() == 'mongodb') && !$this->tableExists) {
      $this->tableExists = $this->ensureTableExists();

      foreach ($keys as &$key) {
        $key = (string) $key;
      }
    }

    // Delete in chunks when a large array is passed.
    while ($keys) {
      try {
        $this->connection->delete($this->table)
          ->condition('name', array_splice($keys, 0, 1000), 'IN')
          ->condition('collection', $this->collection)
          ->execute();
      }
      catch (\Exception $e) {
        $this->catchException($e);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    if (($this->connection->driver() == 'mongodb') && !$this->tableExists) {
      $this->tableExists = $this->ensureTableExists();
    }

    try {
      $this->connection->delete($this->table)
        ->condition('collection', $this->collection)
        ->execute();
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

  /**
   * Check if the table exists and create it if not.
   *
   * @return bool
   *   TRUE if the table exists, FALSE if it does not exists.
   */
  protected function ensureTableExists() {
    try {
      $database_schema = $this->connection->schema();
      $database_schema->createTable($this->table, $this->schemaDefinition());
    }
    // If the table already exists, then attempting to recreate it will throw an
    // exception. In this case just catch the exception and do nothing.
    catch (DatabaseException) {
    }
    catch (\Exception) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Act on an exception when the table might not have been created.
   *
   * If the table does not yet exist, that's fine, but if the table exists and
   * yet the query failed, then the exception needs to propagate if it is not
   * a DatabaseException. Due to race conditions it is possible that another
   * request has created the table in the meantime. Therefore we can not rethrow
   * for any database exception.
   *
   * @param \Exception $e
   *   The exception.
   *
   * @throws \Exception
   */
  protected function catchException(\Exception $e) {
    if (!($e instanceof DatabaseException) && $this->connection->schema()->tableExists($this->table)) {
      throw $e;
    }
  }

  /**
   * Defines the schema for the key_value table.
   */
  public function schemaDefinition() {
    $schema = [
      'description' => 'Generic key-value storage table. See the state system for an example.',
      'fields' => [
        'collection' => [
          'description' => 'A named collection of key and value pairs.',
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
        'name' => [
          'description' => 'The key of the key-value pair. As KEY is a SQL reserved keyword, name was chosen instead.',
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
        'value' => [
          'description' => 'The value.',
          'type' => 'blob',
          'not null' => TRUE,
          'size' => 'big',
        ],
      ],
      'primary key' => ['collection', 'name'],
    ];

    if ($this->connection->driver() == 'mongodb') {
      $schema['fields']['expire']['type'] = 'date';
    }

    return $schema;
  }

}
