<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage;
use Drupal\Core\DrupalKernelInterface;
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
    new Settings([
      'container_base_class' => Container::class,
      'container_dumper_class' => OptimizedPhpArrayDumper::class,
    ]);

    $kernel = $this->createStub(DrupalKernelInterface::class);
    $kernel->method('getAppRoot')->willReturn('/app/root');
    $kernel->method('getSitePath')->willReturn('sites/default');

    return new CacheContainerStorage(
      $cache,
      Settings::getInstance(),
      $kernel,
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
   * Tests that load() returns a container and injects app.root and site.path.
   */
  public function testLoadReturnsContainerOnHitWithInjectedParameters(): void {
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
    $this->assertSame('/app/root', $container->getParameter('app.root'));
    $this->assertSame('sites/default', $container->getParameter('site.path'));
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
    $result = $storage->dump($container, 'test_suffix');

    $this->assertInstanceOf(Container::class, $result);
    // The cached data should not contain app.root and site.path (those are
    // injected at load/dump time).
    $this->assertIsArray($cachedData);
    $this->assertArrayNotHasKey('app.root', $cachedData['parameters'] ?? []);
    $this->assertArrayNotHasKey('site.path', $cachedData['parameters'] ?? []);
    // But the returned container should have them injected.
    $this->assertSame('/app/root', $result->getParameter('app.root'));
    $this->assertSame('sites/default', $result->getParameter('site.path'));
  }

  /**
   * Tests that dump() handles cache exceptions gracefully.
   */
  public function testDumpHandlesCacheException(): void {
    $container = new ContainerBuilder();
    $container->compile();

    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('set')
      ->willThrowException(new \Exception('Cache table does not exist'));

    $storage = $this->createStorage($cache);

    // Should not throw despite cache exception.
    $result = $storage->dump($container, 'test_suffix');
    $this->assertInstanceOf(Container::class, $result);
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
