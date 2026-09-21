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
    // Internal state of the backwards compatibility layer must never leak into
    // the serialized payload.
    $this->assertStringNotContainsString('unserializeData', $string);
    $dependencySerialization = unserialize($string);
    $this->assertInstanceOf(DependencySerializationTestDummy::class, $dependencySerialization);

    $this->assertTrue($container->has(ReverseContainer::class));
    $this->assertSame($service, $dependencySerialization->service);
    $this->assertSame($container, $dependencySerialization->container);
    $this->assertEmpty($dependencySerialization->getServiceIds());
  }

  /**
   * Tests unserializing a payload written by the legacy __sleep() method.
   *
   * Payloads serialized before __serialize() was introduced use PHP's mangled
   * key format for non-public properties ("\0*\0property"). Such payloads may
   * still exist in long-lived storage such as the form cache, tempstore, queue
   * items and batches, and must still be unserialized correctly.
   */
  public function testLegacySerializedData(): void {
    $service = new \stdClass();
    $container = TestKernel::setContainerWithKernel();
    $container->set('test_service', $service);

    // Build the payload exactly as PHP wrote it for a legacy __sleep()
    // implementation: the '_serviceIds' and '_entityStorages' properties are
    // protected and therefore mangled, the 'service' property was replaced by
    // its service ID and the public 'container' property was serialized as-is.
    $class = DependencySerializationTestDummy::class;
    $legacy_service_ids = "\0*\0_serviceIds";
    $legacy_entity_storages = "\0*\0_entityStorages";
    $payload = sprintf(
      'O:%d:"%s":3:{s:9:"container";N;s:%d:"%s";a:1:{s:7:"service";s:12:"test_service";}s:%d:"%s";a:0:{}}',
      strlen($class),
      $class,
      strlen($legacy_service_ids),
      $legacy_service_ids,
      strlen($legacy_entity_storages),
      $legacy_entity_storages,
    );

    $unserialized = unserialize($payload);
    $this->assertInstanceOf(DependencySerializationTestDummy::class, $unserialized);
    $this->assertSame($service, $unserialized->service);
    $this->assertNull($unserialized->container);
    $this->assertEmpty($unserialized->getServiceIds());
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

    $this->expectUserDeprecationMessage('Implementing __sleep() in a subclass of Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy is deprecated in drupal:11.5.0 and is not supported in drupal:13.0.0. Implement __serialize() instead. See https://www.drupal.org/node/3578423');
    $this->expectUserDeprecationMessage('Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy::__sleep() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use __serialize() instead. See https://www.drupal.org/node/3578423');
    $serialized = serialize($sleepWakeup);
    $this->assertStringContainsString('serialized value', $serialized);

    $this->expectUserDeprecationMessage('Implementing __wakeup() in a subclass of Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy is deprecated in drupal:11.5.0 and is not supported in drupal:13.0.0. Implement __unserialize() instead. See https://www.drupal.org/node/3578423');
    $this->expectUserDeprecationMessage('Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy::__wakeup() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use __unserialize() instead. See https://www.drupal.org/node/3578423');
    $unserialized = unserialize($serialized);
    $this->assertInstanceOf(DependencySerializationTestDummySubclass::class, $unserialized);

    $this->assertTrue($container->has(ReverseContainer::class));
    $this->assertSame($service, $unserialized->service);
    $this->assertSame($container, $unserialized->container);
    $this->assertEmpty($unserialized->getServiceIds());
    $this->assertSame('unserialized value', $unserialized->anotherProperty);
  }

  /**
   * Tests a subclass __wakeup() implementation that does not call the parent.
   *
   * Before __unserialize() was introduced, PHP populated the object's
   * properties from the payload and only then called __wakeup(). A subclass
   * whose __wakeup() does not call parent::__wakeup() therefore still got a
   * fully populated object. That behavior must be preserved.
   */
  #[IgnoreDeprecations]
  public function testWakeupWithoutParentCall(): void {
    $service = new \stdClass();
    $container = TestKernel::setContainerWithKernel();
    $container->set('test_service', $service);

    $object = new DependencySerializationTestDummyWakeupOnlySubclass($service);
    $object->setContainer($container);
    $object->anotherProperty = 'serialized value';
    $serialized = serialize($object);

    $this->expectUserDeprecationMessage('Implementing __wakeup() in a subclass of Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy is deprecated in drupal:11.5.0 and is not supported in drupal:13.0.0. Implement __unserialize() instead. See https://www.drupal.org/node/3578423');
    $unserialized = unserialize($serialized);
    $this->assertInstanceOf(DependencySerializationTestDummyWakeupOnlySubclass::class, $unserialized);

    // The properties were populated from the payload before __wakeup() ran.
    $this->assertSame('serialized value (woken up)', $unserialized->anotherProperty);
    // As with the legacy PHP behavior, services are only restored when the
    // subclass calls parent::__wakeup(), which this one does not.
    $this->assertNull($unserialized->service);
    $this->assertSame(['service' => 'test_service', 'container' => 'service_container'], $unserialized->getServiceIds());
  }

  /**
   * Tests a chaining subclass __wakeup() on an object with a readonly property.
   *
   * __unserialize() populates every property, including readonly ones, before
   * it invokes the subclass __wakeup(). When that method chains to the trait's
   * __wakeup(), the properties must not be assigned a second time, as PHP
   * rejects any further write to an initialized readonly property.
   */
  #[IgnoreDeprecations]
  public function testWakeupWithReadonlyProperty(): void {
    $service = new \stdClass();
    $container = TestKernel::setContainerWithKernel();
    $container->set('test_service', $service);

    $object = new DependencySerializationTestDummyReadonlySubclass($service, 'readonly value');
    $object->setContainer($container);
    $serialized = serialize($object);

    $this->expectUserDeprecationMessage('Implementing __wakeup() in a subclass of Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy is deprecated in drupal:11.5.0 and is not supported in drupal:13.0.0. Implement __unserialize() instead. See https://www.drupal.org/node/3578423');
    $this->expectUserDeprecationMessage('Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy::__wakeup() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use __unserialize() instead. See https://www.drupal.org/node/3578423');
    $unserialized = unserialize($serialized);
    $this->assertInstanceOf(DependencySerializationTestDummyReadonlySubclass::class, $unserialized);

    $this->assertSame('readonly value', $unserialized->id);
    $this->assertSame($service, $unserialized->service);
    $this->assertSame($container, $unserialized->container);
    $this->assertEmpty($unserialized->getServiceIds());
  }

  /**
   * Tests a subclass __sleep() that names a private property of the subclass.
   *
   * PHP resolves the names returned by a native __sleep() against the concrete
   * class, so a private property of the subclass is serialized under its
   * mangled key. The backwards compatibility layer must do the same and, on
   * unserialization, assign the property in the scope of the subclass rather
   * than in the scope of the class using the trait.
   */
  #[IgnoreDeprecations]
  public function testSleepWithPrivateProperty(): void {
    $service = new \stdClass();
    $container = TestKernel::setContainerWithKernel();
    $container->set('test_service', $service);

    $object = new DependencySerializationTestDummyPrivateSubclass($service, 'private value');
    $object->setContainer($container);

    $this->expectUserDeprecationMessage('Implementing __sleep() in a subclass of Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy is deprecated in drupal:11.5.0 and is not supported in drupal:13.0.0. Implement __serialize() instead. See https://www.drupal.org/node/3578423');
    $this->expectUserDeprecationMessage('Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy::__sleep() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use __serialize() instead. See https://www.drupal.org/node/3578423');
    $serialized = serialize($object);
    // The private property is serialized under its mangled key, as PHP does
    // for a native __sleep().
    $this->assertStringContainsString("\0" . DependencySerializationTestDummyPrivateSubclass::class . "\0secret", $serialized);

    $unserialized = unserialize($serialized);
    $this->assertInstanceOf(DependencySerializationTestDummyPrivateSubclass::class, $unserialized);
    $this->assertSame('private value', $unserialized->getSecret());
    // The property was assigned directly, not rerouted through __set().
    $this->assertSame([], $unserialized->magicWrites);
    $this->assertSame($service, $unserialized->service);
    $this->assertSame($container, $unserialized->container);
    $this->assertEmpty($unserialized->getServiceIds());
  }

  /**
   * Tests a legacy payload carrying a private property of a subclass.
   *
   * Such payloads were written by PHP for a subclass whose __sleep() named one
   * of its own private properties. The property must be assigned in the scope
   * of the subclass: from the scope of the parent class PHP would either throw
   * or, as the subclass implements __set(), silently reroute the value there.
   */
  public function testLegacySerializedDataWithPrivateProperty(): void {
    $service = new \stdClass();
    $container = TestKernel::setContainerWithKernel();
    $container->set('test_service', $service);

    $class = DependencySerializationTestDummyPrivateSubclass::class;
    $legacy_service_ids = "\0*\0_serviceIds";
    $legacy_entity_storages = "\0*\0_entityStorages";
    $legacy_secret = "\0" . $class . "\0secret";
    $payload = sprintf(
      'O:%d:"%s":4:{s:9:"container";N;s:%d:"%s";a:1:{s:7:"service";s:12:"test_service";}s:%d:"%s";a:0:{}s:%d:"%s";s:12:"legacy value";}',
      strlen($class),
      $class,
      strlen($legacy_service_ids),
      $legacy_service_ids,
      strlen($legacy_entity_storages),
      $legacy_entity_storages,
      strlen($legacy_secret),
      $legacy_secret,
    );

    $unserialized = unserialize($payload);
    $this->assertInstanceOf($class, $unserialized);
    $this->assertSame('legacy value', $unserialized->getSecret());
    $this->assertSame([], $unserialized->magicWrites);
    $this->assertSame($service, $unserialized->service);
    $this->assertNull($unserialized->container);
    $this->assertEmpty($unserialized->getServiceIds());
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

/**
 * Defines a test class whose __wakeup() does not call the parent method.
 */
class DependencySerializationTestDummyWakeupOnlySubclass extends DependencySerializationTestDummy {

  /**
   * Test property that is manipulated in the course of unserialization.
   *
   * @var string
   */
  public string $anotherProperty = 'initial value';

  public function __wakeup(): void {
    $this->anotherProperty .= ' (woken up)';
  }

}

/**
 * Defines a test class with a readonly property and a chaining __wakeup().
 */
class DependencySerializationTestDummyReadonlySubclass extends DependencySerializationTestDummy {

  /**
   * Constructs a new DependencySerializationTestDummyReadonlySubclass object.
   *
   * @param object $service
   *   A test service.
   * @param string $id
   *   A readonly identifier.
   */
  public function __construct(\stdClass $service, public readonly string $id) {
    parent::__construct($service);
  }

  public function __wakeup(): void {
    parent::__wakeup();
  }

}

/**
 * Defines a test class with a private property named in its legacy __sleep().
 *
 * __set() records assignments to properties that are inaccessible from the
 * calling scope. That is where the private property would end up if it were
 * assigned from the scope of the parent class.
 */
class DependencySerializationTestDummyPrivateSubclass extends DependencySerializationTestDummy {

  /**
   * Values assigned through __set(), keyed by property name.
   *
   * @var array<string, mixed>
   */
  public array $magicWrites = [];

  /**
   * Constructs a new DependencySerializationTestDummyPrivateSubclass object.
   *
   * @param object $service
   *   A test service.
   * @param string $secret
   *   A value for the private property.
   *
   * @phpstan-ignore dependencySerializationTraitProperty.unsupportedPrivateProperty
   */
  public function __construct(\stdClass $service, private string $secret = 'default') {
    parent::__construct($service);
  }

  public function __sleep(): array {
    return [...parent::__sleep(), 'secret'];
  }

  public function __set(string $name, mixed $value): void {
    $this->magicWrites[$name] = $value;
  }

  /**
   * Gets the private property.
   */
  public function getSecret(): string {
    return $this->secret;
  }

}
