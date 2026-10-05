<?php

namespace Drupal\Core\Cache;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
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
    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($tags): void {
        foreach ($tags as $tag) {
          $this->connection->upsert('cachetags')
            ->key(['tag'])
            ->fields([
              'tag',
              'invalidations' => new UpsertUpdateExpression('{cachetags}.[invalidations] + 1'),
            ])
            ->values([
              'tag' => $tag,
              'invalidations' => 1,
            ])
            ->execute();
        }
      },
      schema: [
        'cachetags' => $this->schemaDefinition(),
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getTagInvalidationCounts(array $tags) {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($tags): array {
        return $this->connection->query('SELECT [tag], [invalidations] FROM {cachetags} WHERE [tag] IN ( :tags[] )', [':tags[]' => $tags])
          ->fetchAllKeyed();
      },
      schema: [
        'cachetags' => $this->schemaDefinition(),
      ],
    );
    return $execution->isSuccessful() ? $execution->getResult() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function purge(): void {
    // If the table does not exist yet, there is nothing to purge.
    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): void {
        $this->connection->truncate('cachetags')->execute();
      },
      schema: [
        'cachetags' => $this->schemaDefinition(),
      ],
    );
    $this->reset();
  }

  /**
   * Check if the cache tags table exists and create it if not.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   * \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure()
   * instead.
   *
   * @see https://www.drupal.org/node/3489185
   */
  protected function ensureTableExists() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure() instead. See https://www.drupal.org/node/3489185', E_USER_DEPRECATED);
    try {
      $database_schema = $this->connection->schema();
      $schema_definition = $this->schemaDefinition();
      $database_schema->createTable('cachetags', $schema_definition);
    }
    // If another process has already created the cachetags table, attempting to
    // recreate it will throw an exception. In this case just catch the
    // exception and do nothing.
    catch (DatabaseException) {
    }
    catch (\Exception) {
      return FALSE;
    }
    return TRUE;
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
