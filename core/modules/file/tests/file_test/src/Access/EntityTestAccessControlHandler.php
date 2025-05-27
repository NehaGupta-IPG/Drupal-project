<?php

declare(strict_types=1);

namespace Drupal\file_test\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_test\Entity\EntityTestRev;

/**
 * Defines a class for an alternate entity test access control handler.
 */
class EntityTestAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    assert($entity instanceof EntityTestRev);

    if ($operation !== 'view') {
      return AccessResult::neutral();
    }
    return AccessResult::allowedIf($entity->isLatestRevision());
  }

}
