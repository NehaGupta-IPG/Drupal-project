<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\IgbinarySerialize;
use Drupal\Component\Serialization\ObjectAwareSerializationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\DatabaseBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Unit test of the database backend using the generic cache unit test base.
 */
#[Group('Cache')]
#[RunTestsInSeparateProcesses]
class DatabaseBackendTest extends GenericCacheBackendUnitTestBase {

  /**
   * The max rows to use for test bins.
   *
   * @var int
   */
  protected static $maxRows = 100;

  /**
   * Creates a new instance of DatabaseBackend.
   *
   * @return \Drupal\Core\Cache\DatabaseBackend
   *   A new DatabaseBackend object.
   */
  protected function createCacheBackend($bin): DatabaseBackend {
    return new DatabaseBackend(
      $this->container->get('database'),
      $this->container->get('cache_tags.invalidator.checksum'),
      $bin,
      $this->container->get('serialization.igbinary'),
      \Drupal::service(TimeInterface::class),
      static::$maxRows,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function testSetGet(): void {
    parent::testSetGet();
    $backend = $this->getCacheBackend();

    // Set up a cache ID that is not ASCII and longer than 255 characters so we
    // can test cache ID normalization.
    $cid_long = str_repeat('愛€', 500);
    $cached_value_long = $this->randomMachineName();
    $backend->set($cid_long, $cached_value_long);
    $this->assertSame($cached_value_long, $backend->get($cid_long)->data, "Backend contains the correct value for long, non-ASCII cache id.");

    $cid_short = '愛1€';
    $cached_value_short = $this->randomMachineName();
    $backend->set($cid_short, $cached_value_short);
    $this->assertSame($cached_value_short, $backend->get($cid_short)->data, "Backend contains the correct value for short, non-ASCII cache id.");

    // Set multiple items to test exceeding the chunk size.
    $backend->deleteAll();
    $items = [];
    for ($i = 0; $i <= DatabaseBackend::MAX_ITEMS_PER_CACHE_SET; $i++) {
      $items["test$i"]['data'] = $i;
    }
    $backend->setMultiple($items);
    $this->assertSame(DatabaseBackend::MAX_ITEMS_PER_CACHE_SET + 1, $this->getNumRows());
  }

  /**
   * Tests that legacy PHP-serialized cache rows remain readable.
   */
  public function testLegacyPhpSerializedDataRemainsReadable(): void {
    $backend = $this->getCacheBackend();
    $data = [
      'foo' => 'bar',
      'nested' => ['baz' => TRUE],
    ];

    // Prime the database table before inserting a raw legacy row.
    $backend->set('legacy_php_serialized_setup', 'setup');
    $backend->delete('legacy_php_serialized_setup');

    $this->container->get('database')
      ->insert('cache_' . $this->testBin)
      ->fields([
        'cid' => 'legacy_php_serialized',
        'expire' => Cache::PERMANENT,
        'created' => round(microtime(TRUE), 3),
        'tags' => '',
        'checksum' => $this->container->get('cache_tags.invalidator.checksum')->getCurrentChecksum([]),
        'data' => serialize($data),
        'serialized' => 1,
      ])
      ->execute();

    $this->assertSame($data, $backend->get('legacy_php_serialized')->data);
  }

  /**
   * Tests that new cache rows use the igbinary serializer when available.
   */
  #[RequiresFunction('igbinary_serialize')]
  public function testIgbinaryPayloadsAreWrittenWhenAvailable(): void {
    $cid = 'igbinary_payload';
    $data = [
      'foo' => 'bar',
      'nested' => ['baz' => TRUE],
    ];

    $this->getCacheBackend()->set($cid, $data);

    $row = $this->container->get('database')
      ->select('cache_' . $this->testBin, 'c')
      ->fields('c', ['data', 'serialized'])
      ->condition('cid', $cid)
      ->execute()
      ->fetchAssoc();

    $this->assertSame(1, (int) $row['serialized']);
    $this->assertSame(IgbinarySerialize::encode($data), $row['data']);
  }

  /**
   * Tests that serializer decode failures are handled as cache misses.
   */
  public function testSerializerDecodeFailureIsACacheMiss(): void {
    $serializer = new class implements ObjectAwareSerializationInterface {

      /**
       * {@inheritdoc}
       */
      public static function encode($data) {
        return serialize($data);
      }

      /**
       * {@inheritdoc}
       */
      public static function decode($raw) {
        throw new InvalidDataTypeException('Unable to decode cache data.');
      }

      /**
       * {@inheritdoc}
       */
      public static function getFileExtension() {
        return 'serialized';
      }

    };
    $backend = new DatabaseBackend(
      $this->container->get('database'),
      $this->container->get('cache_tags.invalidator.checksum'),
      $this->testBin,
      $serializer,
      \Drupal::service(TimeInterface::class),
      static::$maxRows,
    );
    $backend->set('decode_failure', ['cached' => 'data']);

    $this->assertFalse($backend->get('decode_failure'));
  }

  /**
   * Tests the row count limiting of cache bin database tables.
   */
  public function testGarbageCollection(): void {
    $backend = $this->getCacheBackend();
    $max_rows = static::$maxRows;

    $this->assertSame(0, (int) $this->getNumRows());

    // Fill to just the limit.
    for ($i = 0; $i < $max_rows; $i++) {
      // Ensure that each cache item created happens in a different millisecond,
      // by waiting 1 ms (1000 microseconds). The garbage collection might
      // otherwise keep less than exactly 100 records (which is acceptable for
      // real-world cases, but not for this test).
      usleep(1000);
      $backend->set("test$i", $i);
    }
    $this->assertSame($max_rows, $this->getNumRows());

    // Garbage collection has no effect.
    $backend->garbageCollection();
    $this->assertSame($max_rows, $this->getNumRows());

    // Go one row beyond the limit.
    $backend->set('test' . ($max_rows + 1), $max_rows + 1);
    $this->assertSame($max_rows + 1, $this->getNumRows());

    // Garbage collection removes one row: the oldest.
    $backend->garbageCollection();
    $this->assertSame($max_rows, $this->getNumRows());
    $this->assertFalse($backend->get('test0'));
  }

  /**
   * Gets the number of rows in the test cache bin database table.
   *
   * @return int
   *   The number of rows in the test cache bin database table.
   */
  protected function getNumRows(): int {
    $table = 'cache_' . $this->testBin;
    $connection = $this->container->get('database');
    $query = $connection->select($table);
    $query->addExpression('COUNT([cid])', 'cid');
    return (int) $query->execute()->fetchField();
  }

  /**
   * Tests that "cache_tags.invalidator.checksum" is backend overridable.
   */
  public function testCacheTagsInvalidatorChecksumIsBackendOverridable(): void {
    $definition = $this->container->getDefinition('cache_tags.invalidator.checksum');
    $this->assertTrue($definition->hasTag('backend_overridable'));
  }

  /**
   * Test that the service "cache.backend.database" is backend overridable.
   */
  public function testCacheBackendDatabaseIsBackendOverridable(): void {
    $definition = $this->container->getDefinition('cache.backend.database');
    $this->assertTrue($definition->hasTag('backend_overridable'));
  }

}
