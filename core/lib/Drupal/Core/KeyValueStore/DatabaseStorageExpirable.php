<?php

namespace Drupal\Core\KeyValueStore;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Database\Connection;

/**
 * Defines a default key/value store implementation for expiring items.
 *
 * This key/value store implementation uses the database to store key/value
 * data with an expire date.
 */
class DatabaseStorageExpirable extends DatabaseStorage implements KeyValueStoreExpirableInterface {

  /**
   * Overrides Drupal\Core\KeyValueStore\StorageBase::__construct().
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   The serialization class to use.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param string $table
   *   The name of the SQL table to use, defaults to key_value_expire.
   */
  public function __construct(
    $collection,
    SerializationInterface $serializer,
    Connection $connection,
    protected TimeInterface $time,
    $table = 'key_value_expire',
  ) {
    parent::__construct($collection, $serializer, $connection, $table);
  }

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($key): bool {
        return (bool) $this->connection->query('SELECT 1 FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection AND [name] = :key AND [expire] > :now', [
          ':collection' => $this->collection,
          ':key' => $key,
          ':now' => $this->time->getRequestTime(),
        ])->fetchField();
      },
      schema: [
        $this->table => static::schemaDefinition(),
      ],
    );
    return $execution->isSuccessful() ? $execution->getResult() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys) {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($keys): array {
        $values = $this->connection->query(
          'SELECT [name], [value] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [expire] > :now AND [name] IN ( :keys[] ) AND [collection] = :collection',
          [
            ':now' => $this->time->getRequestTime(),
            ':keys[]' => $keys,
            ':collection' => $this->collection,
          ])->fetchAllKeyed();
        return array_map([$this->serializer, 'decode'], $values);
      },
      schema: [
        $this->table => static::schemaDefinition(),
      ],
    );
    return $execution->isSuccessful() ? $execution->getResult() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): array {
        $values = $this->connection->query(
          'SELECT [name], [value] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection AND [expire] > :now',
          [
            ':collection' => $this->collection,
            ':now' => $this->time->getRequestTime(),
          ])->fetchAllKeyed();
        return array_map([$this->serializer, 'decode'], $values);
      },
      schema: [
        $this->table => static::schemaDefinition(),
      ],
    );
    return $execution->isSuccessful() ? $execution->getResult() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllKeys(): iterable {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): array {
        return $this->connection->query(
          'SELECT [name] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection AND [expire] > :now',
          [
            ':collection' => $this->collection,
            ':now' => $this->time->getRequestTime(),
          ])->fetchCol();
      },
      schema: [
        $this->table => static::schemaDefinition(),
      ],
    );
    return $execution->isSuccessful() ? $execution->getResult() : [];
  }

  /**
   * Saves a value for a given key with a time to live.
   *
   * This will be called by setWithExpire() within a try block.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   * @param int $expire
   *   The time to live for items, in seconds.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *   \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure()
   *   instead.
   *
   * @see https://www.drupal.org/node/3489185
   */
  protected function doSetWithExpire($key, $value, $expire) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure() instead. See https://www.drupal.org/node/3489185', E_USER_DEPRECATED);
    $this->connection->upsert($this->table)
      ->key(['collection', 'name'])
      ->fields([
        'collection' => $this->collection,
        'name' => $key,
        'value' => $this->serializer->encode($value),
        'expire' => $this->time->getRequestTime() + $expire,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function setWithExpire($key, $value, $expire) {
    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($key, $value, $expire): void {
        $this->connection->upsert($this->table)
          ->key(['collection', 'name'])
          ->fields([
            'collection' => $this->collection,
            'name' => $key,
            'value' => $this->serializer->encode($value),
            'expire' => $this->time->getRequestTime() + $expire,
          ])
          ->execute();
      },
      schema: [
        $this->table => static::schemaDefinition(),
      ],
      retryAfterSchemaEnsured: TRUE,
    );
  }

  /**
   * Sets a value for a given key with a time to live if it does not yet exist.
   *
   * This will be called by setWithExpireIfNotExists() within a try block.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   * @param int $expire
   *   The time to live for items, in seconds.
   *
   * @return bool
   *   TRUE if the data was set, or FALSE if it already existed.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *   \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure()
   *   instead.
   *
   * @see https://www.drupal.org/node/3489185
   */
  protected function doSetWithExpireIfNotExists($key, $value, $expire) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure() instead. See https://www.drupal.org/node/3489185', E_USER_DEPRECATED);
    if (!$this->has($key)) {
      $this->setWithExpire($key, $value, $expire);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setWithExpireIfNotExists($key, $value, $expire) {
    if (!$this->has($key)) {
      $this->setWithExpire($key, $value, $expire);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultipleWithExpire(array $data, $expire) {
    foreach ($data as $key => $value) {
      $this->setWithExpire($key, $value, $expire);
    }
  }

  /**
   * Defines the schema for the key_value_expire table.
   */
  public static function schemaDefinition() {
    return [
      'description' => 'Generic key/value storage table with an expiration.',
      'fields' => [
        'collection' => [
          'description' => 'A named collection of key and value pairs.',
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
        'name' => [
          // KEY is an SQL reserved word, so use 'name' as the key's field name.
          'description' => 'The key of the key/value pair.',
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
        'value' => [
          'description' => 'The value of the key/value pair.',
          'type' => 'blob',
          'not null' => TRUE,
          'size' => 'big',
        ],
        'expire' => [
          'description' => 'The time since Unix epoch in seconds when this item expires. Defaults to the maximum possible time.',
          'type' => 'int',
          'not null' => TRUE,
          'default' => 2147483647,
        ],
      ],
      'primary key' => ['collection', 'name'],
      'indexes' => [
        'expire' => ['expire'],
      ],
    ];
  }

}
