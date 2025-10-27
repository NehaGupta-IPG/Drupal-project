<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\DependencyInjection\Attribute\ServiceClosure;
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
    // Add a property to the object to differentiate it from the next service
    // object.
    $service->serviceId = 'test_service';
    $container = TestKernel::setContainerWithKernel();
    $container->set($service->serviceId, $service);
    $service2 = new \stdClass();
    // Set property to be different from first service.
    $service2->serviceId = 'test_service2';
    $container->set($service2->serviceId, $service2);
    $service2Closure = DependencySerializationTestDummy::getServiceClosure($service2->serviceId, $container);
    $this->assertSame($container, $container->get('service_container'));
    $dependencySerialization = new DependencySerializationTestDummy($service, $service2Closure);
    $dependencySerialization->setContainer($container);

    $string = serialize($dependencySerialization);
    /** @var \Drupal\Tests\Core\DependencyInjection\DependencySerializationTestDummy $dependencySerialization */
    $dependencySerialization = unserialize($string);

    $this->assertTrue($container->has(ReverseContainer::class));
    $this->assertSame($service, $dependencySerialization->service);
    $this->assertNotEquals($service2, $dependencySerialization->service);
    $this->assertSame($service2, ($dependencySerialization->closure)());
    $this->assertNotEquals($service, ($dependencySerialization->closure)());
    $this->assertSame($container, $dependencySerialization->container);
    $this->assertEmpty($dependencySerialization->getServiceIds());
    $this->assertEmpty($dependencySerialization->getServiceClosureIds());

    // Test that exceptions are thrown when attempting to serialize class
    // properties that are closures that DependencySerializationTrait does not
    // handle.
    $closures = [
      // Closure without the
      // Drupal\Core\DependencyInjection\Attribute\ServiceClosure attribute.
      static fn() => $service2,
      // Closure that returns an object that is not a registered service object.
      #[ServiceClosure] static fn() => new \stdClass(),
    ];
    $countThrown = 0;
    foreach ($closures as $closure) {
      $throwable = NULL;
      $dependencySerialization->closure = $closure;
      try {
        serialize($dependencySerialization);
      }
      catch (\Throwable $throwable) {
        $countThrown++;
      }
      finally {
        $this->assertInstanceOf(\Exception::class, $throwable);
        $this->assertSame("Serialization of 'Closure' is not allowed", $throwable->getMessage());
      }
    }
    $this->assertEquals(count($closures), $countThrown);
  }

}

/**
 * Defines a test class which has a single service as dependency.
 */
class DependencySerializationTestDummy {

  use DependencySerializationTrait;

  public function __construct(
    public \stdClass $service,
    public \Closure $closure,
    public ?ContainerInterface $container = NULL,
  ) {}

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

  /**
   * Gets the stored service IDs for closure properties.
   */
  public function getServiceClosureIds(): array {
    return $this->_serviceClosureIds;
  }

}
