<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\DependencyInjection\PhpContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Factory for creating the container storage service.
 *
 * Determines which ContainerStorageInterface implementation to use based on
 * the 'container_base_class' setting. Sites that need a custom storage
 * strategy can override the 'container_storage' service in the bootstrap
 * container definition via the 'bootstrap_container_definition' setting.
 *
 * @see \Drupal\Core\DrupalKernel::$defaultBootstrapContainerDefinition
 */
class ContainerStorageFactory {

  /**
   * Creates the container storage service.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The bootstrap container.
   *
   * @return \Drupal\Core\DependencyInjection\ContainerStorageInterface
   *   The container storage service.
   */
  public static function create(ContainerInterface $container): ContainerStorageInterface {
    $baseClass = $container->get('settings')->get('container_base_class', Container::class);

    if (is_a($baseClass, PhpContainer::class, TRUE)) {
      return $container->get('container_storage.php_dumper');
    }

    return $container->get('container_storage.cache');
  }

}
