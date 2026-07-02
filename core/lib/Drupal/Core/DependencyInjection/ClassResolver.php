<?php

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the class resolver interface supporting class names and services.
 */
class ClassResolver implements ClassResolverInterface {

  use DependencySerializationTrait;

  /**
   * Constructs a new ClassResolver object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  public function __construct(protected ContainerInterface $container) {
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceFromDefinition($definition) {
    if ($this->container->has($definition)) {
      // Re-sync the synthetic kernel onto a stale container builder during
      // install so OOP-hook services autowiring @kernel can be constructed.
      if ($this->container instanceof ContainerBuilder
          && !$this->container->initialized('kernel') && \Drupal::hasContainer()) {
        $this->container->set('kernel', \Drupal::service('kernel'));
      }
      $instance = $this->container->get($definition);
    }
    else {
      if (!class_exists($definition)) {
        throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $definition));
      }

      if (is_subclass_of($definition, 'Drupal\Core\DependencyInjection\ContainerInjectionInterface')) {
        $instance = $definition::create($this->container);
      }
      else {
        $instance = new $definition();
      }
    }

    return $instance;
  }

}
