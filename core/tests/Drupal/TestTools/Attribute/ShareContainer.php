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
 * service providers that build it. A test class whose container depends on
 * anything else must declare #[ShareContainer(FALSE)]. So must a test class
 * that inspects service definitions, because a shared container does not have
 * them.
 *
 * A test class that registers services in ::register() or alters them does not
 * share its container by default, because ::register() often adds objects
 * that only exist in the process that compiled the container. Such a test
 * class can declare #[ShareContainer] to share its container with itself. The
 * test class is then part of the identifier.
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
   * @param bool $default
   *   (optional) Whether the class shares its container if neither it nor its
   *   parents declare the attribute. Defaults to TRUE.
   *
   * @return bool
   *   The value that the class or its closest parent declares, or the default.
   */
  public static function isEnabledFor(string $class, bool $default = TRUE): bool {
    for ($reflection = new \ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
      $attributes = $reflection->getAttributes(self::class);
      if ($attributes !== []) {
        return $attributes[0]->newInstance()->enabled;
      }
    }
    return $default;
  }

}
