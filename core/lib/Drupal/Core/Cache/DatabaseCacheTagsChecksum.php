<?php

namespace Drupal\Core\Cache;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
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
    $reconnected = FALSE;
    $table_created = FALSE;
    foreach ($tags as $tag) {
      while (TRUE) {
        try {
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
          break;
        }
        catch (\TypeError $e) {
          if ($reconnected) {
            throw $e;
          }
          // A destroyed Connection cannot satisfy its object return contract.
          // A TypeError from invalidation alone does not establish client loss.
          try {
            $this->connection->getClientConnection();
          }
          catch (\TypeError) {
            $target = $this->connection->getTarget();
            $key = $this->connection->getKey();
            Database::closeConnection($target, $key);
            $this->connection = Database::getConnection($target, $key);
            $this->tagCache = [];
            $reconnected = TRUE;
            continue;
          }
          throw $e;
        }
        catch (DatabaseException $e) {
          // Only a missing table permits the installation-time fallback.
          // An existing table must not turn a failed write into success.
          if ($table_created || $this->connection->schema()->tableExists('cachetags')) {
            throw $e;
          }
          $table_created = TRUE;
          if (!$this->ensureTableExists()) {
            throw $e;
          }
          // Retry this tag, never replay tags already processed successfully.
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getTagInvalidationCounts(array $tags) {
    try {
      return $this->connection->query('SELECT [tag], [invalidations] FROM {cachetags} WHERE [tag] IN ( :tags[] )', [':tags[]' => $tags])
        ->fetchAllKeyed();
    }
    catch (\Exception $e) {
      // If the table does not exist yet, create.
      if (!$this->ensureTableExists()) {
        throw $e;
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function purge(): void {
    try {
      $this->connection->truncate('cachetags')->execute();
    }
    catch (\Throwable $e) {
      // If the table does not exist yet, there is nothing to purge.
      if (!$this->ensureTableExists()) {
        throw $e;
      }
    }
    $this->reset();
  }

  /**
   * Check if the cache tags table exists and create it if not.
   */
  protected function ensureTableExists() {
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
