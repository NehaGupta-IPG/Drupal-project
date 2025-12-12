<?php

declare(strict_types=1);

namespace Drupal\CustomInstaller;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

final class InstallerServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->register('custom_installer_hooks', Hooks::class);
  }

}
