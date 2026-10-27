<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Fixture;

/**
 * Stub class standing in for an object-oriented hook implementation class.
 *
 * Hook classes are registered in the container under their own fully qualified
 * class name and receive their dependencies through the constructor, so they
 * cannot be instantiated with `new`.
 *
 * @see \Drupal\Tests\Core\DependencyInjection\ClassResolverTest
 */
class StubHookClass {

  /**
   * Constructs a StubHookClass object.
   *
   * @param object $dependency
   *   A required constructor dependency.
   */
  public function __construct(protected object $dependency) {
  }

  /**
   * Returns the injected dependency.
   *
   * @return object
   *   The dependency this object was constructed with.
   */
  public function getDependency(): object {
    return $this->dependency;
  }

}
