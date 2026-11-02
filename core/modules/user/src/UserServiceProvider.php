<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Service provider for the User module.
 */
final class UserServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->registerForAutoconfiguration(PermissionProviderInterface::class)
      ->addTag('user.permission_provider');
  }

}
