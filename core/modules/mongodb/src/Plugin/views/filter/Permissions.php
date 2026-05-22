<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\user\Entity\Role;
use Drupal\user\Plugin\views\filter\Permissions as CorePermissions;
use Drupal\user\RoleInterface;

/**
 * Overriding the views filter plugin "user_permissions".
 */
class Permissions extends CorePermissions {

  use InOperatorTrait;
  use ManyToOneTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $rids = [];
    $all_roles = Role::loadMultiple();
    // Get all role IDs that have the configured permissions.
    foreach ($this->value as $permission) {
      $roles = array_filter($all_roles, fn(RoleInterface $role) => $role->hasPermission($permission));
      // user_role_names() returns an array with the role IDs as keys, so take
      // the array keys and merge them with previously found role IDs.
      $rids = array_merge($rids, array_keys($roles));
    }
    // Remove any duplicate role IDs.
    $rids = array_unique($rids);
    $this->value = $rids;

    // The role field is located in an embedded table.
    $this->realField = 'user_translations.user_translations__roles.roles_target_id';

    // $this->value contains the role IDs that have the configured permission.
    if ($this->table == $this->view->storage->get('base_table')) {
      $this->mongodbField = $this->realField;
    }
    elseif (!empty($this->relationship)) {
      $this->mongodbField = "$this->relationship.$this->realField";
    }
    else {
      // Throw an exception.
      $this->mongodbField = $this->realField;
    }

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}();
    }
  }

}
