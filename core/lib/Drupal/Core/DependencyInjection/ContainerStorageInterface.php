<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for loading, dumping, and invalidating service containers.
 *
 * The implementation is auto-detected from the 'container_base_class' setting.
 * Sites that need a custom storage strategy can override the
 * 'container_storage' service in the bootstrap container definition
 * via the 'bootstrap_container_definition' setting in settings.php.
 *
 * @see \Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage
 * @see \Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage
 */
interface ContainerStorageInterface {

  /**
   * Attempts to load a previously stored container.
   *
   * @param string $identifier
   *   A hash identifying the container version, computed by the kernel
   *   from the environment, service providers, module filenames, etc.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface|null
   *   The loaded container, or NULL if no valid cached container exists.
   */
  public function load(string $identifier): ?ContainerInterface;

  /**
   * Dumps a compiled container to persistent storage.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The compiled container builder.
   * @param string $identifier
   *   A hash identifying this container version.
   */
  public function dump(ContainerBuilder $container, string $identifier): void;

  /**
   * Invalidates all stored containers.
   */
  public function invalidate(): void;

  /**
   * Removes stored containers that are no longer in use.
   *
   * @param string $identifier
   *   A hash identifying the current container version.
   */
  public function garbageCollection(string $identifier): void;

}
