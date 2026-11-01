<?php

namespace Drupal\user_dynamic_permissions_config_entity_test;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\Role;

/**
 * The DynamicConfigEntityPermissions class.
 */
class DynamicConfigEntityPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of permissions.
   *
   * @return array
   */
  public function permissions(): array {
    $roles = Role::loadMultiple();
    $permissions = [];
    foreach ($roles as $rid => $role) {
      $permissions[sprintf('%s role permission', $rid)] = [
        'title' => $this->t('%s role permission', ['%role' => $role->label()]),
      ];
    }

    return $permissions;
  }

}
