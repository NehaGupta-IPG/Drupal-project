<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * No-op container storage that never loads or persists containers.
 *
 * Used during installation (before the database is available) and by
 * UpdateKernel (which always rebuilds from scratch and should not persist
 * its container to avoid leaking update-specific services).
 */
class NullContainerStorage implements ContainerStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function load(string $suffix): ?ContainerInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $suffix): ContainerInterface {
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    // No-op.
  }

}
