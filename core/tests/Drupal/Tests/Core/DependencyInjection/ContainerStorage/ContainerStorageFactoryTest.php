<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage;
use Drupal\Core\DependencyInjection\ContainerStorage\ContainerStorageFactory;
use Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Core\Site\Settings;
use Drupal\FunctionalTests\Bootstrap\ErrorContainer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the ContainerStorageFactory.
 */
#[CoversClass(ContainerStorageFactory::class)]
#[Group('DependencyInjection')]
class ContainerStorageFactoryTest extends UnitTestCase {

  /**
   * Tests that the storage is selected by the container base class.
   *
   * @param string|null $base_class
   *   The container_base_class setting, or NULL to leave it unset.
   * @param string $expected_service
   *   The ID of the storage service the factory is expected to return.
   */
  #[TestWith([NULL, 'container_storage.cache'], 'unset')]
  #[TestWith([Container::class, 'container_storage.cache'], 'array container')]
  #[TestWith([PhpContainer::class, 'container_storage.php_dumper'], 'PhpContainer')]
  #[TestWith(['\\' . PhpContainer::class, 'container_storage.php_dumper'], 'PhpContainer with leading backslash')]
  #[TestWith([ErrorContainer::class, 'container_storage.php_dumper'], 'PhpContainer subclass')]
  public function testCreate(?string $base_class, string $expected_service): void {
    new Settings($base_class === NULL ? [] : ['container_base_class' => $base_class]);

    $services = [
      'settings' => Settings::getInstance(),
      'container_storage.cache' => $this->createStub(CacheContainerStorage::class),
      'container_storage.php_dumper' => $this->createStub(PhpDumperContainerStorage::class),
    ];
    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')
      ->willReturnCallback(fn (string $id) => $services[$id]);

    $this->assertSame($services[$expected_service], ContainerStorageFactory::create($container));
  }

}
