<?php

declare(strict_types=1);

namespace Drupal\TestTools\Attribute;

/**
 * Controls whether a kernel test shares its compiled container.
 *
 * When the DRUPAL_TEST_CONTAINER_DIRECTORY environment variable names a
 * directory, kernel tests store the container they boot with in it. Other
 * tests that would compile the same container load it instead. The test runner
 * sets the variable for each run, unless it is set already. An empty value
 * turns sharing off.
 *
 * A stored container is identified by the modules, the service files and the
 * service providers that build it. When a test class registers services in
 * ::register(), the test class is part of the identifier as well. A test class
 * whose container depends on anything else, such as state that changes between
 * its test methods, must declare #[ShareContainer(FALSE)]. So must a test
 * class that tests how the container is compiled.
 *
 * Subclasses inherit the attribute.
 *
 * @see \Drupal\KernelTests\KernelTestBase::sharesContainer()
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ShareContainer {

  /**
   * Constructs a ShareContainer object.
   *
   * @param bool $enabled
   *   (optional) Whether the test class shares its compiled container.
   */
  public function __construct(public readonly bool $enabled = TRUE) {}

  /**
   * Returns whether a class shares its compiled container.
   *
   * @param string $class
   *   The name of the class to check.
   *
   * @return bool
   *   FALSE if the class or one of its parents declares that it does not share
   *   its container, TRUE otherwise.
   */
  public static function isEnabledFor(string $class): bool {
    for ($reflection = new \ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
      $attributes = $reflection->getAttributes(self::class);
      if ($attributes !== []) {
        return $attributes[0]->newInstance()->enabled;
      }
    }
    return TRUE;
  }

}
