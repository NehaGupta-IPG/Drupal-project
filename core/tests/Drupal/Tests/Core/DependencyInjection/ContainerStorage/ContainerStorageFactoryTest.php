<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage;
use Drupal\Core\DependencyInjection\ContainerStorage\ContainerStorageFactory;
use Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Tests that CacheContainerStorage is returned for the legacy base class.
   */
  public function testCreatesCacheStorageForLegacyBaseClass(): void {
    new Settings([
      'container_base_class' => Container::class,
    ]);

    $cacheStorage = $this->createStub(CacheContainerStorage::class);
    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')
      ->willReturnMap([
        ['settings', Settings::getInstance()],
        ['container_storage.cache', $cacheStorage],
      ]);

    $storage = ContainerStorageFactory::create($container);
    $this->assertSame($cacheStorage, $storage);
  }

  /**
   * Tests that PhpDumperContainerStorage is returned for PhpContainer base.
   */
  public function testCreatesPhpDumperStorageForPhpContainerBase(): void {
    new Settings([
      'container_base_class' => PhpContainer::class,
    ]);

    $phpDumperStorage = $this->createStub(PhpDumperContainerStorage::class);
    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')
      ->willReturnMap([
        ['settings', Settings::getInstance()],
        ['container_storage.php_dumper', $phpDumperStorage],
      ]);

    $storage = ContainerStorageFactory::create($container);
    $this->assertSame($phpDumperStorage, $storage);
  }

}
