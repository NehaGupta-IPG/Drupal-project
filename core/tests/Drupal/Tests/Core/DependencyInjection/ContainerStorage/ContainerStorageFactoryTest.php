<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\DependencyInjection\Container;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage;
use Drupal\Core\DependencyInjection\ContainerStorage\ContainerStorageFactory;
use Drupal\Core\DependencyInjection\ContainerStorage\NullContainerStorage;
use Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage;
use Drupal\Core\DependencyInjection\DumpedContainer;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ContainerStorageFactory.
 *
 * Tests run in separate processes because the \Drupal\Core\Site\Settings
 * singleton is used.
 */
#[CoversClass(ContainerStorageFactory::class)]
#[Group('DependencyInjection')]
#[RunTestsInSeparateProcesses]
class ContainerStorageFactoryTest extends UnitTestCase {

  /**
   * Tests that CacheContainerStorage is created for the legacy base class.
   */
  public function testCreatesCacheStorageForLegacyBaseClass(): void {
    new Settings([
      'hash_salt' => 'test_hash_salt',
      'container_base_class' => Container::class,
    ]);

    $cache = $this->createStub(CacheBackendInterface::class);
    $storage = ContainerStorageFactory::create($cache);
    $this->assertInstanceOf(CacheContainerStorage::class, $storage);
  }

  /**
   * Tests that PhpDumperContainerStorage is created for DumpedContainer base.
   */
  public function testCreatesPhpDumperStorageForDumpedContainerBase(): void {
    new Settings([
      'hash_salt' => 'test_hash_salt',
      'container_base_class' => DumpedContainer::class,
    ]);

    $cache = $this->createStub(CacheBackendInterface::class);
    $storage = ContainerStorageFactory::create($cache);
    $this->assertInstanceOf(PhpDumperContainerStorage::class, $storage);
  }

  /**
   * Tests that NullContainerStorage is returned when PhpStorage throws.
   */
  public function testFallsBackToNullOnPhpStorageFailure(): void {
    // Without hash_salt, PhpStorageFactory::get() throws a RuntimeException.
    new Settings([
      'container_base_class' => DumpedContainer::class,
    ]);

    $cache = $this->createStub(CacheBackendInterface::class);
    $storage = ContainerStorageFactory::create($cache);
    $this->assertInstanceOf(NullContainerStorage::class, $storage);
  }

  /**
   * Tests that an explicit container_storage_class setting is respected.
   */
  public function testExplicitStorageClassOverride(): void {
    new Settings([
      'hash_salt' => 'test_hash_salt',
      'container_storage_class' => NullContainerStorage::class,
    ]);

    $cache = $this->createStub(CacheBackendInterface::class);
    $storage = ContainerStorageFactory::create($cache);
    $this->assertInstanceOf(NullContainerStorage::class, $storage);
  }

}
