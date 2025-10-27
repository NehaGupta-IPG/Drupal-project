<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\Attribute\ServiceClosure;

/**
 * Trait that provides a method that returns a service closure for an ID.
 */
trait ServiceClosureTrait {

  /**
   * Gets a closure that returns the service associated with ID.
   *
   * @param string $serviceId
   *   The service ID.
   * @param \Drupal\Component\DependencyInjection\ContainerInterface|null $container
   *   The container.
   *
   * @return \Closure
   *   The service closure.
   */
  public static function getServiceClosure(string $serviceId, ?ContainerInterface $container = NULL): \Closure {
    $container ??= \Drupal::getContainer();
    if (!$container->has($serviceId)) {
      throw new \InvalidArgumentException("$serviceId is not a valid service ID.");
    }
    // Apply ServiceClosure attribute to the closure to make it identifiable as
    // a service closure.
    return #[ServiceClosure] fn () => $container->get($serviceId);
  }

}
