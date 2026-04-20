<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\DrupalKernelInterface;
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
  public function load(string $suffix, DrupalKernelInterface $kernel): ?ContainerInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $suffix, DrupalKernelInterface $kernel): ContainerInterface {
    return $container;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    // No-op.
  }

}
