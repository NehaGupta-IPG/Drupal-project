<?php

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for loading, dumping, and invalidating service containers.
 *
 * Implementations of this interface handle the persistence of compiled service
 * containers. The DrupalKernel uses a container storage instance created by
 * ContainerStorageFactory to load cached containers, dump newly compiled ones,
 * and invalidate stale containers.
 *
 * The implementation is swappable via the 'container_storage_class' setting in
 * settings.php, or auto-detected from the 'container_base_class' setting. For
 * example, a site could ship a pre-built container in Git by providing a
 * custom implementation.
 *
 * @see \Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage
 * @see \Drupal\Core\DependencyInjection\ContainerStorage\CacheContainerStorage
 * @see \Drupal\Core\DependencyInjection\ContainerStorage\NullContainerStorage
 */
interface ContainerStorageInterface {

  /**
   * Attempts to load a previously stored container.
   *
   * @param string $suffix
   *   A hash suffix identifying the container version, computed by the kernel
   *   from the environment, service providers, module filenames, etc.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface|null
   *   The loaded container, or NULL if no valid cached container exists.
   */
  public function load(string $suffix): ?ContainerInterface;

  /**
   * Dumps a compiled container to persistent storage.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The compiled container builder.
   * @param string $suffix
   *   A hash suffix identifying this container version.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container instance to use at runtime. This may be a new container
   *   instantiated from the dumped representation, or the original
   *   ContainerBuilder if dumping is not possible.
   */
  public function dump(ContainerBuilder $container, string $suffix): ContainerInterface;

  /**
   * Invalidates all stored containers.
   */
  public function invalidate(): void;

}
