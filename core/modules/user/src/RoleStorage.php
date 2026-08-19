<?php

namespace Drupal\user;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the storage handler class for user roles.
 */
class RoleStorage extends ConfigEntityStorage implements RoleStorageInterface {

  /**
   * {@inheritdoc}
   */
  protected function mapToStorageRecord(EntityInterface $entity) {
    $data = parent::mapToStorageRecord($entity);
    if ($entity instanceof RoleInterface && $entity->getDescription() === '') {
      unset($data['description']);
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function isPermissionInRoles($permission, array $rids) {
    foreach ($this->loadMultiple($rids) as $role) {
      /** @var \Drupal\user\RoleInterface $role */
      if ($role->hasPermission($permission)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
