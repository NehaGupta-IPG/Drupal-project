<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

use Drupal\Core\DependencyInjection\Attribute\ServiceClosure;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SerializableServiceClosure {

  /**
   * Create a recognizable service closure.
   *
   * @param string $service_id
   *   The service ID.
   * @param \Drupal\Component\DependencyInjection\ContainerInterface|null $container
   *   The optional service container.
   *
   * @return \Closure
   *   The resulting closure.
   */
  public static function create(string $service_id, ?ContainerInterface $container = NULL): \Closure {
    $container ??= \Drupal::getContainer();
    return #[ServiceClosure]
    function() use ($service_id, $container) {
      return $container->resolveServices($service_id);
    };
  }

  public static function check(\Closure $closure): bool {
    $reflection_closure = new \ReflectionFunction($closure);
    return !empty($reflection_closure->getAttributes(ServiceClosure::class));
  }

}
