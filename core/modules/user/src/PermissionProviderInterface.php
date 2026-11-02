<?php

declare(strict_types=1);

namespace Drupal\user;

/**
 * Defines an interface for services that provide dynamic permissions.
 *
 * Any service implementing this interface is automatically tagged
 * 'user.permission_provider' and its permissions are merged with those
 * declared in *.permissions.yml files.
 */
interface PermissionProviderInterface {

  /**
   * Returns an array of dynamic permissions.
   *
   * @return array
   *   An array of permissions in the same format returned by
   *   \Drupal\user\PermissionHandlerInterface::getPermissions(),
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function getPermissions(): array;

}
