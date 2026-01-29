<?php

namespace Drupal\Core\Controller\ArgumentResolver;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Yields a service based on the argument type passed along.
 */
final class ServiceValueResolver implements ValueResolverInterface {

  public function __construct(
    private ContainerInterface $container,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(Request $request, ArgumentMetadata $argument): array {
    foreach ($argument->getAttributes(Autowire::class) as $attribute) {
      $service_id = (string) $attribute->value;
      if ($this->container->has($service_id)) {
        return [$this->container->get($service_id)];
      }
    }

    $type = $argument->getType();
    if ($type !== NULL && $this->container->has($type)) {
      return [$this->container->get($type)];
    }

    return [];
  }

}
