<?php

namespace Drupal\Core\DependencyInjection;

/**
 * Provides interface to get an instance of a class with dependency injection.
 */
interface ClassResolverInterface {

  /**
   * Returns a class instance with a given class definition.
   *
   * @param class-string<T>|string $definition
   *   A class name or service name. Note that services are often registered
   *   as a class or interface name as well. If it is not a service,
   *   \Drupal\Core\DependencyInjection\ContainerInjectionInterface can be used
   *   for dependency injection, if it is neither, it must not have a
   *   constructor.
   *
   * @template T of object
   *
   * @return ($definition is class-string<T> ? T : object)
   *   The instance of the class.
   *
   * @throws \InvalidArgumentException
   *   If $class is not a valid service identifier and the class does not exist.
   */
  public function getInstanceFromDefinition($definition);

}
