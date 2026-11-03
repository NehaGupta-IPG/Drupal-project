<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\PreWarm\PreWarmableInterface;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\pgsql\Driver\Database\pgsql\TableInformationLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the caching of the blob and sequence table information.
 *
 * @see \Drupal\pgsql\Driver\Database\pgsql\TableInformationLoader
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class TableInformationCacheTest extends DriverSpecificKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->connection->schema()->createTable('test_table_info', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'data' => ['type' => 'blob', 'size' => 'big'],
      ],
      'primary key' => ['id'],
    ]);
  }

  /**
   * Returns a new loader without any collected table information.
   *
   * The connection's own loader keeps its in-memory state for the whole
   * test; a fresh loader simulates the cold start of a new request.
   */
  protected function freshLoader(): TableInformationLoader {
    return new TableInformationLoader($this->connection);
  }

  /**
   * Returns the cache ID used for the table information of one schema.
   */
  protected function getCacheId(string $schema = 'public'): string {
    $loader = new TableInformationLoader($this->connection);
    $method = new \ReflectionMethod($loader, 'getCacheId');
    return $method->invoke($loader, $schema);
  }

  /**
   * Tests that the table information is cached and invalidated.
   */
  public function testTableInformationCache(): void {
    $cid = $this->getCacheId();
    $key = 'public.' . $this->connection->getPrefix() . 'test_table_info';
    $bootstrap = \Drupal::cache('bootstrap');

    // The first lookup on a cold cache populates the cache bin.
    $bootstrap->delete($cid);
    $info = $this->freshLoader()->getTableInformation('test_table_info');
    $this->assertSame(['data' => TRUE], $info->blob_fields);
    $this->assertSame(['id'], $info->serial_fields);
    $this->assertCount(1, $info->sequences);
    $this->assertArrayHasKey($key, $bootstrap->get($cid)->data);

    // The cache is authoritative: tampered cache data is served as is.
    $tampered = $bootstrap->get($cid)->data;
    $tampered[$key]->blob_fields = ['tampered' => TRUE];
    $bootstrap->set($cid, $tampered);
    $this->assertSame(['tampered' => TRUE], $this->freshLoader()->getTableInformation('test_table_info')->blob_fields);

    // A schema change invalidates the cache, and the next lookup collects
    // the current information again.
    $this->connection->schema()->addField('test_table_info', 'extra', ['type' => 'blob']);
    $this->assertFalse($bootstrap->get($cid));
    $info = $this->freshLoader()->getTableInformation('test_table_info');
    $this->assertSame(['data' => TRUE, 'extra' => TRUE], $info->blob_fields);
  }

  /**
   * Tests that creating a table invalidates the cached table information.
   */
  public function testCreateTableInvalidatesCache(): void {
    $cid = $this->getCacheId();
    $bootstrap = \Drupal::cache('bootstrap');

    // Warm the caches.
    $this->freshLoader()->getTableInformation('test_table_info');
    $this->assertNotFalse($bootstrap->get($cid));

    $this->connection->schema()->createTable('test_late', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
      ],
      'primary key' => ['id'],
    ]);
    $this->assertFalse($bootstrap->get($cid));

    // The next load includes the new table.
    $info = $this->freshLoader()->getTableInformation('test_late');
    $this->assertSame(['id'], $info->serial_fields);
    $key = 'public.' . $this->connection->getPrefix() . 'test_late';
    $this->assertArrayHasKey($key, $bootstrap->get($cid)->data);
  }

  /**
   * Tests that the information is not persisted during a transaction.
   *
   * PostgreSQL DDL is transactional, so information collected during a
   * transaction must not end up in cache backends that do not roll back with
   * the database.
   */
  public function testNoPersistentCacheInTransaction(): void {
    $cid = $this->getCacheId();
    $bootstrap = \Drupal::cache('bootstrap');
    $bootstrap->delete($cid);

    $transaction = $this->connection->startTransaction();
    $info = $this->freshLoader()->getTableInformation('test_table_info');
    $this->assertSame(['data' => TRUE], $info->blob_fields);
    $this->assertFalse($bootstrap->get($cid));
    $transaction->commitOrRelease();

    // After the transaction the information is persisted again.
    $this->freshLoader()->getTableInformation('test_table_info');
    $this->assertNotFalse($bootstrap->get($cid));
  }

  /**
   * Tests that a missing table returns empty information and is not cached.
   */
  public function testMissingTable(): void {
    $schema = $this->connection->schema();

    $info = $schema->queryTableInformation('test_missing');
    $this->assertSame([], $info->blob_fields);
    $this->assertSame([], $info->sequences);

    // The empty information must not stick: once the table exists, the same
    // schema object returns its real information.
    $schema->createTable('test_missing', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'data' => ['type' => 'blob', 'size' => 'big'],
      ],
      'primary key' => ['id'],
    ]);
    $info = $schema->queryTableInformation('test_missing');
    $this->assertSame(['data' => TRUE], $info->blob_fields);
    $this->assertSame(['id'], $info->serial_fields);
  }

  /**
   * Tests the introspection of a temporary table through the pg_temp alias.
   */
  public function testTemporaryTable(): void {
    $table_name = $this->connection->queryTemporary('SELECT [id], [data] FROM {test_table_info}');
    $info = $this->connection->schema()->queryTableInformation($table_name);
    $this->assertSame(['data' => TRUE], $info->blob_fields);
    $this->assertSame([], $info->sequences);
  }

  /**
   * Tests loading the table information of another schema.
   */
  public function testLoadAllOtherSchema(): void {
    $prefix = $this->connection->getPrefix();
    $other_schema = $prefix . 'other';
    $this->connection->query('CREATE SCHEMA ' . $other_schema);
    try {
      $this->connection->query('CREATE TABLE ' . $other_schema . '."' . $prefix . 'other_table" (id serial PRIMARY KEY, data bytea)');

      $loader = new TableInformationLoader($this->connection);
      $loader->loadAll($other_schema);

      $info = $loader->get($other_schema . '.' . $prefix . 'other_table');
      $this->assertSame(['data' => TRUE], $info->blob_fields);
      $this->assertSame(['id'], $info->serial_fields);

      // Each schema is loaded and cached separately: loading the other
      // schema neither loads the default schema nor touches its cache entry.
      $this->assertNull($loader->get('public.' . $prefix . 'test_table_info'));
      $this->assertNotFalse(\Drupal::cache('bootstrap')->get($this->getCacheId($other_schema)));
      $this->assertFalse(\Drupal::cache('bootstrap')->get($this->getCacheId()));

      // The default schema loads independently afterwards.
      $loader->loadAll();
      $this->assertNotNull($loader->get('public.' . $prefix . 'test_table_info'));
    }
    finally {
      $this->connection->query('DROP SCHEMA ' . $other_schema . ' CASCADE');
    }
  }

  /**
   * Tests that the pgsql.table_information_prewarmer service prewarms the cache.
   */
  public function testPreWarm(): void {
    $cid = $this->getCacheId();
    $bootstrap = \Drupal::cache('bootstrap');
    $bootstrap->delete($cid);

    $service = \Drupal::service('pgsql.table_information_prewarmer');
    $this->assertInstanceOf(PreWarmableInterface::class, $service);
    $service->preWarm();

    $key = 'public.' . $this->connection->getPrefix() . 'test_table_info';
    $this->assertArrayHasKey($key, $bootstrap->get($cid)->data);
  }

}
