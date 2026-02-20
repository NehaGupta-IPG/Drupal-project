<?php

declare(strict_types=1);

namespace Drupal\workflows;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the interface for workflow state field items.
 */
interface WorkflowStateItemInterface extends FieldItemInterface {

  /**
   * Gets the workflow used by the field.
   *
   * @return \Drupal\workflows\WorkflowInterface|null
   *   The workflow entity, or NULL if the workflow is not known yet.
   *
   * @throws \LogicException
   *   If the workflow type plugin does not implement
   *   \Drupal\workflows\EntityWorkflowTypeInterface.
   */
  public function getWorkflow(): ?WorkflowInterface;

  /**
   * Gets the current state ID.
   *
   * @return string|null
   *   The current state ID, or NULL if not yet set.
   */
  public function getStateId(): ?string;

  /**
   * Gets the label of the current state.
   *
   * @return string
   *   The label of the current state, or an empty string if unknown.
   */
  public function getStateLabel(): string;

  /**
   * Gets the state ID as it was when the entity was loaded or created.
   *
   * Unlike reading from $entity->original, this value is tracked directly
   * on the field item and is always available.
   *
   * @return string|null
   *   The original state ID, or NULL if not yet set.
   */
  public function getOriginalStateId(): ?string;

  /**
   * Gets the label of the original state.
   *
   * If the state ID has been changed after the entity was constructed/loaded,
   * this returns the label of the previous state.
   *
   * @return string
   *   The label of the original state, or an empty string if unknown.
   */
  public function getOriginalStateLabel(): string;

  /**
   * Gets the allowed transitions for the current state.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account to check access for, or NULL for the current user.
   *
   * @return \Drupal\workflows\TransitionInterface[]
   *   The allowed transitions, keyed by transition ID.
   */
  public function getTransitions(?AccountInterface $account = NULL): array;

  /**
   * Determines whether the given transition is allowed from the current state.
   *
   * This checks both structural validity (transition exists from the current
   * state) and access (the user has permission to perform the transition via
   * EntityWorkflowTypeInterface).
   *
   * @param \Drupal\workflows\TransitionInterface $transition
   *   The transition to check.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account to check access for, or NULL for the current user.
   *
   * @return bool
   *   TRUE if the given transition is allowed, FALSE otherwise.
   */
  public function isTransitionAllowed(TransitionInterface $transition, ?AccountInterface $account = NULL): bool;

  /**
   * Applies the given transition, changing the current state.
   *
   * @param \Drupal\workflows\TransitionInterface $transition
   *   The transition to apply.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the transition is not allowed from the current state, either
   *   because the transition does not originate from this state or because the
   *   current user lacks access.
   */
  public function applyTransition(TransitionInterface $transition): void;

  /**
   * Applies a transition with the given ID, changing the current state.
   *
   * @param string $transition_id
   *   The transition ID.
   *
   * @throws \InvalidArgumentException
   *   Thrown when no matching transition was found or when the transition
   *   is not allowed from the current state.
   */
  public function applyTransitionById(string $transition_id): void;

  /**
   * Gets the transition for the current state change.
   *
   * Returns the explicitly applied transition if available, otherwise looks up
   * the transition from the original state to the current state.
   *
   * @return \Drupal\workflows\TransitionInterface|null
   *   The transition, or NULL if no state change occurred or no valid
   *   transition exists.
   */
  public function getCurrentTransition(): ?TransitionInterface;

  /**
   * Determines whether the current state is valid.
   *
   * Returns TRUE if the state exists in the workflow and, when a state change
   * has occurred, a valid and accessible transition exists.
   *
   * @return bool
   *   TRUE if the current state is valid, FALSE otherwise.
   */
  public function isValid(): bool;

}
