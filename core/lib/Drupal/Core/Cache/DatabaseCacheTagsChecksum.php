<?php

namespace Drupal\Core\Cache;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\UpsertUpdateExpression;

/**
 * Cache tags invalidations checksum implementation that uses the database.
 */
class DatabaseCacheTagsChecksum implements CacheTagsChecksumInterface, CacheTagsInvalidatorInterface, CacheTagsChecksumPreloadInterface, CacheTagsPurgeInterface {

  use CacheTagsChecksumTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a DatabaseCacheTagsChecksum object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  protected function doInvalidateTags(array $tags) {
    foreach ($tags as $tag) {
      $this->connection->executeAndCreateTableIfNeeded(
        'cachetags',
        fn() => $this->connection->schema()->createTable('cachetags', $this->schemaDefinition()),
        fn() => $this->connection->upsert('cachetags')
          ->key(['tag'])
          ->fields([
            'tag',
            'invalidations' => new UpsertUpdateExpression('{cachetags}.[invalidations] + 1'),
          ])
          ->values([
            'tag' => $tag,
            'invalidations' => 1,
          ])
          ->execute(),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getTagInvalidationCounts(array $tags) {
    return $this->connection->executeAndCreateTableIfNeeded(
      'cachetags',
      fn() => $this->connection->schema()->createTable('cachetags', $this->schemaDefinition()),
      fn() => $this->connection->query('SELECT [tag], [invalidations] FROM {cachetags} WHERE [tag] IN ( :tags[] )', [':tags[]' => $tags])->fetchAllKeyed(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function purge(): void {
    $this->connection->executeAndCreateTableIfNeeded(
      'cachetags',
      fn() => $this->connection->schema()->createTable('cachetags', $this->schemaDefinition()),
      fn() => $this->connection->truncate('cachetags')->execute(),
    );
    $this->reset();
  }

  /**
   * Defines the schema for the {cachetags} table.
   *
   * @internal
   */
  public function schemaDefinition() {
    $schema = [
      'description' => 'Cache table for tracking cache tag invalidations.',
      'fields' => [
        'tag' => [
          'description' => 'Namespace-prefixed tag string.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'invalidations' => [
          'description' => 'Number incremented when the tag is invalidated.',
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => ['tag'],
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatabaseConnection() {
    return $this->connection;
  }

}
