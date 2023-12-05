<?php

namespace Drupal\content_moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for providing validation for states that can be reverted to.
 */
interface StateRevertValidationInterface {

  /**
   * Get valid states that an entity can be reverted to.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The entity revision being reverted to.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The account that wants to revert the entity.
   *
   * @return \Drupal\workflows\StateInterface[]
   *   The states that can be reverted to.
   */
  public function getValidRevertStates(ContentEntityInterface $revision, AccountInterface $user);

}
