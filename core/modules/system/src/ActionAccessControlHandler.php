<?php

namespace Drupal\system;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the action entity type.
 */
class ActionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $action, $operation, AccountInterface $account) {
    $admin_permission = $action->getEntityType()->getAdminPermission();
    // Ask also the plugin.
    /** @var \Drupal\system\ActionConfigEntityInterface $action */
    $plugin_result = AccessResult::allowedIf($action->getPlugin()->userAccess($operation, $account));
    return AccessResult::allowedIfHasPermission($account, $admin_permission)->orIf($plugin_result);
  }

}
