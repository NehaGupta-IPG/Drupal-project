<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Test\TestKernel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests Drupal\Core\DependencyInjection\DependencySerializationTrait.
 */
#[CoversClass(DependencySerializationTrait::class)]
#[Group('DependencyInjection')]
class DependencySerializationTest extends UnitTestCase {

  /**
   * Tests serialization.
   *
   * @legacy-covers ::__serialize
   * @legacy-covers ::__unserialize
   */
  public function testSerialization(): void {
    // Create a pseudo service and dependency injected object.
    $service = new \stdClass();
    $container = TestKernel::setContainerWithKernel();
    $container->set('test_service', $service);
    $this->assertSame($container, $container->get('service_container'));

    $dependencySerialization = new DependencySerializationTestDummy($service);
    $dependencySerialization->setContainer($container);

    $string = serialize($dependencySerialization);
    $dependencySerialization = unserialize($string);
    $this->assertInstanceOf(DependencySerializationTestDummy::class, $dependencySerialization);

    $this->assertTrue($container->has(ReverseContainer::class));
    $this->assertSame($service, $dependencySerialization->service);
    $this->assertSame($container, $dependencySerialization->container);
    $this->assertEmpty($dependencySerialization->getServiceIds());
  }

  /**
   * Test deprecation and backward compatibility of __sleep and __wakeup.
   */
  #[IgnoreDeprecations]
  public function testSleepWakeupDeprecation(): void {
    // Create a pseudo service and dependency injected object.
    $service = new \stdClass();
    $container = TestKernel::setContainerWithKernel();
    $container->set('test_service', $service);
    $this->assertSame($container, $container->get('service_container'));

    // Test deprecations and backwards compatibility layer for __sleep() and
    // __wakeup().
    $sleepWakeup = new DependencySerializationTestDummySubclass($service);
    $sleepWakeup->setContainer($container);

    $this->expectUserDeprecationMessage('Implementing __sleep() in a subclass of Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy is deprecated in drupal:11.4.0 and is not supported in drupal:13.0.0. Implement __serialize() instead. See https://www.drupal.org/node/3578423');
    $this->expectUserDeprecationMessage('Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy::__sleep() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use __serialize() instead. See https://www.drupal.org/node/3578423');
    $serialized = serialize($sleepWakeup);
    $this->assertStringContainsString('serialized value', $serialized);

    $this->expectUserDeprecationMessage('Implementing __wakeup() in a subclass of Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy is deprecated in drupal:11.4.0 and is not supported in drupal:13.0.0. Implement __unserialize() instead. See https://www.drupal.org/node/3578423');
    $this->expectUserDeprecationMessage('Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy::__wakeup() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use __unserialize() instead. See https://www.drupal.org/node/3578423');
    $unserialized = unserialize($serialized);
    $this->assertInstanceOf(DependencySerializationTestDummySubclass::class, $unserialized);

    $this->assertTrue($container->has(ReverseContainer::class));
    $this->assertSame($service, $unserialized->service);
    $this->assertSame($container, $unserialized->container);
    $this->assertEmpty($unserialized->getServiceIds());
    $this->assertSame('unserialized value', $unserialized->anotherProperty);
  }

}

/**
 * Defines a test class which has a single service as dependency.
 */
class DependencySerializationTestDummy {

  use DependencySerializationTrait;

  /**
   * A test service.
   *
   * @var object
   */
  public $service;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  public $container;

  /**
   * Constructs a new TestClass object.
   *
   * @param object $service
   *   A test service.
   */
  public function __construct(\stdClass $service) {
    $this->service = $service;
  }

  /**
   * {@inheritdoc}
   */
  public function setContainer(?ContainerInterface $container): void {
    $this->container = $container;
  }

  /**
   * Gets the stored service IDs.
   */
  public function getServiceIds(): array {
    return $this->_serviceIds;
  }

}

/**
 * Defines a test class which has deprecated __sleep() and __wakeup() methods.
 */
class DependencySerializationTestDummySubclass extends DependencySerializationTestDummy {

  /**
   * Test property that is manipulated in the course of serialization.
   *
   * @var string
   */
  public string $anotherProperty = 'initial value';

  public function __sleep(): array {
    $this->anotherProperty = 'serialized value';
    return parent::__sleep();
  }

  public function __wakeup(): void {
    parent::__wakeup();
    $this->anotherProperty = 'unserialized value';
  }

}
