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
      if ($this->container->has($attribute->value)) {
        return [$this->container->get($attribute->value)];
      }
    }

    if ($this->container->has($argument->getType())) {
      return [$this->container->get($argument->getType())];
    }

    return [];
  }

}
