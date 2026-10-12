<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Test;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\DependencyInjection\Compiler\RuntimeParametersPass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Site\Settings;
use Drupal\Core\Test\TestContainerStorage;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the TestContainerStorage.
 */
#[CoversClass(TestContainerStorage::class)]
#[Group('Test')]
class TestContainerStorageTest extends UnitTestCase {

  /**
   * Tests that a stored container can be loaded with other identifiers.
   */
  public function testDumpAndLoad(): void {
    vfsStream::setup('containers');
    $directory = vfsStream::url('containers');
    $storage = new TestContainerStorage($directory, new Settings([]), ['site.path' => 'sites/runtime']);
    $this->assertNull($storage->load('shared:first'));

    $builder = new ContainerBuilder();
    $builder->setParameter('test_param', $this->randomMachineName());
    $builder->setParameter('site.path', 'sites/compiled');
    $builder->register('service', \ArrayObject::class)
      ->addArgument(['%site.path%']);
    $builder->register('keyvalue', KeyValueMemoryFactory::class);
    $builder->register('datetime.time', Time::class);
    $builder->register('cache.bootstrap', MemoryBackend::class)
      ->addArgument(new Reference('datetime.time'));
    $builder->addCompilerPass(new RuntimeParametersPass(['site.path']), PassConfig::TYPE_AFTER_REMOVING);
    $builder->compile();
    // Compiling the container writes the hook implementations.
    $builder->get('keyvalue')->get('hook_data')->set('hook_list', ['hook' => []]);

    $storage->dump($builder, 'shared:first');
    $storage->dump($builder, 'shared:second');
    // Both identifiers share one container file.
    $this->assertCount(1, preg_grep('/\.php$/', scandir($directory)));

    // Loading a shared container writes the hook implementations.
    $container = $storage->load('shared:second');
    $this->assertSame(['sites/runtime'], $container->get('service')->getArrayCopy());
    $this->assertSame(['hook' => []], $container->get('keyvalue')->get('hook_data')->get('hook_list'));

    // Other identifiers belong to the site, and are removed when the site
    // invalidates its container.
    $other_site_storage = new TestContainerStorage($directory, new Settings([]), ['site.path' => 'sites/other']);
    $storage->addIdentifier('site', 'shared:first');
    $this->assertNotNull($storage->load('site'));
    $this->assertNull($other_site_storage->load('site'));
    $this->assertNotNull($other_site_storage->load('shared:first'));
    $storage->invalidate();
    $this->assertNull($storage->load('site'));
    $this->assertNotNull($storage->load('shared:first'));
  }

  /**
   * Tests that a container that cannot be dumped is not stored.
   */
  public function testDumpFailure(): void {
    vfsStream::setup('containers');
    $storage = new TestContainerStorage(vfsStream::url('containers'), new Settings([]));

    $builder = new ContainerBuilder();
    $builder->register('service', \ArrayObject::class)
      ->addArgument([new \stdClass()]);
    $builder->compile();

    $storage->dump($builder, 'identifier');
    $this->assertNull($storage->load('identifier'));
  }

}
