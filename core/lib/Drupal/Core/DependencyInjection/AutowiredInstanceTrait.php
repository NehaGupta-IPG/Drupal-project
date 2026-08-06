<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

/**
 * Defines a base trait for automatically wiring dependency arguments.
 */
trait AutowiredInstanceTrait {

  /**
   * Instantiates a new instance of the implementing class using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   * @param mixed ...$args
   *   Any predefined arguments to pass to the constructor.
   *
   * @return static
   */
  public static function createInstanceAutowired(ContainerInterface $container, mixed ...$args): static {
    return AutowireFactory::createInstanceAutowired($container, static::class, $args);
  }

}
