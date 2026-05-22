<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\views\field\Roles as CoreRoles;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Overriding the views field plugin "user_roles".
 */
class Roles extends CoreRoles {

  use FieldPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['uid'] = ['table' => 'users', 'field' => 'uid'];
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $uids = [];
    $this->items = [];

    foreach ($values as $result) {
      $uids[] = $this->getValue($result);
    }

    if ($uids) {
      $roles = Role::loadMultiple();

      $result = [];
      foreach ($values as $row) {
        if (($row->_entity instanceof User) && ($uid = $row->_entity->id()) && in_array($uid, $uids)) {
          foreach ($row->_entity->getRoles() as $rid) {
            if (in_array($rid, array_keys($roles))) {
              $role = new \stdClass();
              $role->uid = $uid;
              $role->rid = $rid;
              $result[] = $role;
            }
          }
        }
      }

      foreach ($result as $role) {
        $this->items[$role->uid][$role->rid]['role'] = $roles[$role->rid]->label();
        $this->items[$role->uid][$role->rid]['rid'] = $role->rid;
      }
      // Sort the roles for each user by role weight.
      $ordered_roles = array_flip(array_keys($roles));
      foreach ($this->items as &$user_roles) {
        // Create an array of rids that the user has in the role weight order.
        $sorted_keys = array_intersect_key($ordered_roles, $user_roles);
        // Merge with the unsorted array of role information which has the
        // effect of sorting it.
        $user_roles = array_replace($sorted_keys, $user_roles);
      }
    }
  }

}
