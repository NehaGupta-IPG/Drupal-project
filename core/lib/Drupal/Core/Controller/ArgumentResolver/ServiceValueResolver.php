<?php

namespace Drupal\Core\Controller\ArgumentResolver;

use Psr\Container\ContainerInterface;
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
    if (!$this->container->has($argument->getType())) {
      return [];
    }

    return [$this->container->get($argument->getType())];
  }

}
