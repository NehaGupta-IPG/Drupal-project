<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the CacheContainerStorage.
 *
 * Tests run in separate processes because the \Drupal\Core\Site\Settings
 * singleton is used.
 */
#[CoversClass(CacheContainerStorage::class)]
#[Group('DependencyInjection')]
#[RunTestsInSeparateProcesses]
class CacheContainerStorageTest extends UnitTestCase {

  /**
   * Creates a CacheContainerStorage with default settings.
   */
  protected function createStorage(CacheBackendInterface $cache): CacheContainerStorage {
    new Settings(['container_base_class' => Container::class]);

    return new CacheContainerStorage(
      $cache,
      Settings::getInstance(),
    );
  }

  /**
   * Tests that load() returns NULL on cache miss.
   */
  public function testLoadReturnsNullOnCacheMiss(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('get')
      ->with('service_container:test_suffix')
      ->willReturn(FALSE);

    $storage = $this->createStorage($cache);
    $this->assertNull($storage->load('test_suffix'));
  }

  /**
   * Tests that load() returns a container.
   */
  public function testLoadReturnsContainerOnHit(): void {
    $definition = [
      'aliases' => [],
      'parameters' => ['some_param' => 'value'],
      'services' => [],
      'frozen' => TRUE,
      'machine_format' => TRUE,
    ];

    $cache = $this->createMock(CacheBackendInterface::class);
    $cacheItem = (object) ['data' => $definition];
    $cache->expects($this->once())
      ->method('get')
      ->with('service_container:test_suffix')
      ->willReturn($cacheItem);

    $storage = $this->createStorage($cache);
    $container = $storage->load('test_suffix');

    $this->assertInstanceOf(Container::class, $container);
    $this->assertSame('value', $container->getParameter('some_param'));
  }

  /**
   * Tests that dump() serializes and caches the container definition.
   */
  public function testDumpSerializesAndCaches(): void {
    $container = new ContainerBuilder();
    $container->setParameter('test', 'value');
    $container->compile();

    $cache = $this->createMock(CacheBackendInterface::class);
    $cachedData = NULL;
    $cache->expects($this->once())
      ->method('set')
      ->willReturnCallback(function ($key, $data) use (&$cachedData) {
        $this->assertSame('service_container:test_suffix', $key);
        $cachedData = $data;
      });

    $storage = $this->createStorage($cache);
    $storage->dump($container, 'test_suffix');

    $this->assertIsArray($cachedData);
    $this->assertSame('value', $cachedData['parameters']['test']);
  }

  /**
   * Tests that dump() propagates cache exceptions (e.g. a broken database).
   */
  public function testDumpPropagatesCacheException(): void {
    $container = new ContainerBuilder();
    $container->compile();

    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('set')
      ->willThrowException(new \Exception('An error occurred while writing to the cache'));

    $storage = $this->createStorage($cache);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('An error occurred while writing to the cache');
    $storage->dump($container, 'test_suffix');
  }

  /**
   * Tests that dump() uses a custom dumper class when provided.
   */
  public function testDumpUsesCustomDumperClass(): void {
    $container = new ContainerBuilder();
    $container->setParameter('test', 'value');
    $container->compile();

    $cache = $this->createMock(CacheBackendInterface::class);
    $cachedData = NULL;
    $cache->expects($this->once())
      ->method('set')
      ->willReturnCallback(function ($key, $data) use (&$cachedData) {
        $cachedData = $data;
      });

    new Settings([
      'container_base_class' => Container::class,
    ]);

    // Use a custom dumper that injects a marker parameter to prove it was used.
    $customDumperClass = get_class(new class ($container) extends OptimizedPhpArrayDumper {

      public function getArray() {
        $array = parent::getArray();
        $array['parameters']['_custom_dumper_marker'] = TRUE;
        return $array;
      }

    });

    $storage = new CacheContainerStorage(
      $cache,
      Settings::getInstance(),
      $customDumperClass,
    );
    $storage->dump($container, 'test_suffix');

    $this->assertIsArray($cachedData);
    // The marker proves the custom dumper class was used, not the default.
    $this->assertTrue($cachedData['parameters']['_custom_dumper_marker']);
  }

  /**
   * Tests that invalidate() calls deleteAll on the cache backend.
   */
  public function testInvalidateDeletesAll(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('deleteAll');

    $storage = $this->createStorage($cache);
    $storage->invalidate();
  }

}
