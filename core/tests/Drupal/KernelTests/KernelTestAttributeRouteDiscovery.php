<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Drupal\Core\Routing\AttributeRouteDiscovery;

/**
 * Discovers routes in a kernel test class using Symfony's Route attribute.
 *
 * @internal
 */
class KernelTestAttributeRouteDiscovery extends AttributeRouteDiscovery {

  /**
   * Constructs a KernelTestAttributeRouteDiscovery object.
   *
   * @param \Drupal\KernelTests\KernelTestBase $kernelTest
   *   The kernel test.
   */
  public function __construct(protected readonly KernelTestBase $kernelTest) {}

  /**
   * {@inheritdoc}
   */
  protected function collectRoutes(): iterable {
    $reflectionClass = $this->getReflectionClass($this->kernelTest::class);
    if ($reflectionClass !== NULL) {
      yield $this->createControllerRouteCollection($reflectionClass);
    }
  }

}
