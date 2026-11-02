<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Test\TestKernel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
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
   * @legacy-covers ::__sleep
   * @legacy-covers ::__wakeup
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
    /** @var \Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy $dependencySerialization */
    $dependencySerialization = unserialize($string);

    $this->assertTrue($container->has(ReverseContainer::class));
    $this->assertSame($service, $dependencySerialization->service);
    $this->assertSame($container, $dependencySerialization->container);
    $this->assertEmpty($dependencySerialization->getServiceIds());
  }

  /**
   * Tests serialization of objects with virtual properties.
   *
   * @legacy-covers ::__sleep
   */
  public function testVirtualProperties(): void {
    $object = new DependencySerializationVirtualPropertyTestDummy();

    // Virtual properties have no stored value, so __sleep() must not return
    // them, and must not run their get hooks either.
    $this->assertEqualsCanonicalizing(['hookCalls', 'stored', 'secret', '_serviceIds', '_entityStorages'], $object->__sleep());
    $this->assertSame(0, $object->hookCalls);

    // Serializing must not cause a warning about the virtual property.
    set_error_handler(function (int $errno, string $errstr): never {
      throw new \ErrorException($errstr, 0, $errno);
    });
    try {
      $string = serialize($object);
    }
    finally {
      restore_error_handler();
    }

    /** @var \Drupal\Tests\Core\DependencyInjection\DependencySerializationVirtualPropertyTestDummy $object */
    $object = unserialize($string);
    $this->assertSame('stored value', $object->stored);
    $this->assertSame('secret value', $object->getSecret());
    $this->assertSame('virtual value', $object->virtual);
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
