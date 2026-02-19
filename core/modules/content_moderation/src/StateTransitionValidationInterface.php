<?php

namespace Drupal\content_moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
interface StateTransitionValidationInterface {

  /**
   * Gets a list of transitions that are legal for this user on this entity.
   *
   * @param ContentEntityInterface $entity
   *   The entity to be transitioned.
   * @param AccountInterface $user
   *   The account that wants to perform a transition.
   *
   * @return \Drupal\workflows\Transition[]
   *   The list of transitions that are legal for this user on this entity.
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user);

  /**
   * Checks if a transition between two states if valid for the given user.
   *
   * @param WorkflowInterface $workflow
   *   The workflow entity.
   * @param StateInterface $original_state
   *   The original workflow state.
   * @param StateInterface $new_state
   *   The new workflow state.
   * @param AccountInterface $user
   *   The user to validate.
   * @param ContentEntityInterface $entity
   *   The entity to be transitioned.
   *
   * @return bool
   *   Returns TRUE if transition is valid, otherwise FALSE.
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, AccountInterface $user, ContentEntityInterface $entity);

}
