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
      // This resolver's own container can also be stale relative to the
      // live container (e.g. after a mid-request container rebuild
      // triggered by installing a module), so $this->container->has()
      // above returns FALSE even though the service is defined. Blindly
      // falling through to new $definition() then breaks any autowired
      // class requiring constructor arguments with an ArgumentCountError.
      // Prefer the live container's compiled definition in that case.
      if (\Drupal::hasContainer() && \Drupal::getContainer() !== $this->container && \Drupal::getContainer()->has($definition)) {
        return \Drupal::getContainer()->get($definition);
      }

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
