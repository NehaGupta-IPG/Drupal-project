<?php

namespace Drupal\user;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;

@trigger_error('The ' . __NAMESPACE__ . '\RoleStorageInterface is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3477235', E_USER_DEPRECATED);

/**
 * Defines an interface for role entity storage classes.
 *
 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
 *    replacement.
 * @see https://www.drupal.org/node/3477235
 */
interface RoleStorageInterface extends ConfigEntityStorageInterface {

  /**
   * Returns whether a permission is in one of the passed in roles.
   *
   * @param string $permission
   *   The permission.
   * @param array $rids
   *   The list of role IDs to check.
   *
   * @return bool
   *   TRUE is the permission is in at least one of the roles. FALSE otherwise.
   */
  public function isPermissionInRoles($permission, array $rids);

}
