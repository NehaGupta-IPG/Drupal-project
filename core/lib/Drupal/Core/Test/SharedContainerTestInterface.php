<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for a test that registers itself as a service provider.
 *
 * A kernel test registers itself as a service provider, so its container
 * depends on the test. When tests share their containers, the kernel asks the
 * test for a key that identifies the services it adds. As the test registers
 * services on the container builder, which is not used when a stored container
 * is loaded, the kernel passes each new container to the test.
 *
 * @see \Drupal\Core\Test\TestDrupalKernel
 * @see \Drupal\KernelTests\KernelTestBase
 *
 * @internal
 */
interface SharedContainerTestInterface {

  /**
   * Returns a key for the services that the test adds to the container.
   *
   * @return string
   *   A key that changes whenever the services that the test adds to the
   *   container change.
   */
  public function getContainerKey(): string;

  /**
   * Sets a new container on the test.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  public function setContainer(ContainerInterface $container): void;

}
