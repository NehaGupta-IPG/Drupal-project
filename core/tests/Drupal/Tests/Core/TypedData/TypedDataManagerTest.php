<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\TypedData;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the typed data manager.
 */
#[CoversClass(TypedDataManager::class)]
#[Group('TypedData')]
class TypedDataManagerTest extends UnitTestCase {

  /**
   * Tests the manager is serializable while holding prototypes.
   *
   * The prototype cache is a \WeakMap, which cannot be serialized even when it
   * is empty, so TypedDataManager::__sleep() has to drop it. This exercises the
   * path without a container, where DependencySerializationTrait::__sleep()
   * catches ContainerNotInitializedException and returns every property.
   */
  public function testSerializationWithoutContainer(): void {
    \Drupal::unsetContainer();

    $manager = (new \ReflectionClass(TypedDataManager::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($manager, 'prototypes'))->setValue($manager, new \WeakMap());

    $this->assertNotContains('prototypes', $manager->__sleep());
    $unserialized = unserialize(serialize($manager));
    $this->assertInstanceOf(TypedDataManager::class, $unserialized);
    $this->assertNull((new \ReflectionProperty($unserialized, 'prototypes'))->getValue($unserialized));
  }

  /**
   * Tests the manager is serializable with a container.
   *
   * This is the branch of DependencySerializationTrait::__sleep() that swaps
   * injected services for their service IDs. The prototype cache has to be
   * dropped there too, and is NULL again after unserialization so that the
   * next lookup rebuilds it.
   */
  public function testSerializationWithContainer(): void {
    $container = new ContainerBuilder();
    $container->set(ReverseContainer::class, new ReverseContainer($container));
    \Drupal::setContainer($container);

    $manager = (new \ReflectionClass(TypedDataManager::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($manager, 'prototypes'))->setValue($manager, new \WeakMap());

    $this->assertNotContains('prototypes', $manager->__sleep());
    $unserialized = unserialize(serialize($manager));
    $this->assertInstanceOf(TypedDataManager::class, $unserialized);
    $this->assertNull((new \ReflectionProperty($unserialized, 'prototypes'))->getValue($unserialized));
  }

}
