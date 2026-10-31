<?php

declare(strict_types=1);

namespace Drupal\workflows;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for workflow types attached to entities.
 */
interface EntityWorkflowTypeInterface extends WorkflowTypeInterface {

  /**
   * Checks whether a user has access to perform a transition.
   *
   * @param \Drupal\workflows\TransitionInterface $transition
   *   The transition to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity the workflow is attached to.
   * @param string $field_name
   *   The name of the workflow state field.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkTransitionAccess(TransitionInterface $transition, AccountInterface $account, FieldableEntityInterface $entity, string $field_name): AccessResultInterface;

}
