<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\DependencyInjection\Compiler\RuntimeParametersPass;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

/**
 * Tests the CacheContainerStorage.
 */
#[CoversClass(CacheContainerStorage::class)]
#[Group('DependencyInjection')]
class CacheContainerStorageTest extends UnitTestCase {

  /**
   * The cache backend.
   */
  protected MemoryBackend $cache;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    new Settings(['container_base_class' => Container::class]);
    $time = $this->createStub(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000);
    $this->cache = new MemoryBackend($time);
  }

  /**
   * Creates a compiled container builder with a test parameter.
   */
  protected function createContainerBuilder(): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->setParameter('test', 'value');
    $container->compile();
    return $container;
  }

  /**
   * Tests that load() returns NULL when nothing is stored.
   */
  public function testLoadReturnsNullOnCacheMiss(): void {
    $storage = new CacheContainerStorage($this->cache, Settings::getInstance());
    $this->assertNull($storage->load('test_identifier'));
  }

  /**
   * Tests that a dumped container can be loaded again.
   */
  public function testDumpAndLoad(): void {
    $storage = new CacheContainerStorage($this->cache, Settings::getInstance());
    $storage->dump($this->createContainerBuilder(), 'test_identifier');

    // The cache ID matches the one used by the deprecated
    // \Drupal\Core\DrupalKernel::getContainerCacheKey().
    $this->assertNotFalse($this->cache->get('service_container:test_identifier'));

    $container = $storage->load('test_identifier');
    $this->assertInstanceOf(Container::class, $container);
    $this->assertSame('value', $container->getParameter('test'));
    $this->assertNull($storage->load('other_identifier'));
  }

  /**
   * Tests that runtime parameters are set when the container is loaded.
   */
  public function testRuntimeParameters(): void {
    $builder = new ContainerBuilder();
    $builder->setParameter('site.path', 'sites/compiled');
    $builder->register('service', \ArrayObject::class)
      ->setPublic(TRUE)
      ->addArgument(['%site.path%']);
    $builder->addCompilerPass(new RuntimeParametersPass(['site.path']), PassConfig::TYPE_AFTER_REMOVING);
    $builder->compile();

    $storage = new CacheContainerStorage($this->cache, Settings::getInstance(), runtimeParameters: ['site.path' => 'sites/runtime']);
    $storage->dump($builder, 'test_identifier');

    $container = $storage->load('test_identifier');
    $this->assertSame('sites/runtime', $container->getParameter('site.path'));
    $this->assertSame(['sites/runtime'], $container->get('service')->getArrayCopy());
  }

  /**
   * Tests that dump() uses a custom dumper class when provided.
   */
  public function testDumpUsesCustomDumperClass(): void {
    $container = $this->createContainerBuilder();

    // Use a custom dumper that injects a marker parameter to prove it was used.
    $customDumperClass = get_class(new class ($container) extends OptimizedPhpArrayDumper {

      public function getArray() {
        $array = parent::getArray();
        $array['parameters']['_custom_dumper_marker'] = TRUE;
        return $array;
      }

    });

    $storage = new CacheContainerStorage($this->cache, Settings::getInstance(), $customDumperClass);
    $storage->dump($container, 'test_identifier');

    $this->assertTrue($storage->load('test_identifier')->getParameter('_custom_dumper_marker'));
  }

  /**
   * Tests that garbageCollection() removes expired items from the cache.
   */
  public function testGarbageCollection(): void {
    $storage = new CacheContainerStorage($this->cache, Settings::getInstance());
    $storage->dump($this->createContainerBuilder(), 'test_identifier');
    $this->cache->set('expired', 'data', 500);

    $storage->garbageCollection('test_identifier');

    $this->assertFalse($this->cache->get('expired', TRUE));
    $this->assertInstanceOf(Container::class, $storage->load('test_identifier'));
  }

}
