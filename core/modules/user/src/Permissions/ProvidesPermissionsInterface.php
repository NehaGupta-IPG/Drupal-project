<?php

declare(strict_types=1);

namespace Drupal\user\Permissions;

/**
 * Defines an interface for classes that provide permissions.
 *
 * @see the user_permissions_repository_test module for an example.
 */
interface ProvidesPermissionsInterface {

  /**
   * Return an array of permissions, keyed by machine name.
   *
   * @return array<string, array<string, mixed>>
   *   Array of permissions.
   */
  public function getPermissions(): array;

}
