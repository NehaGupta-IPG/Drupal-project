<?php

namespace Drupal\content_moderation;

use Drupal\workflows\StateInterface;

/**
 * A value object representing a workflow state for content moderation.
 */
class ContentModerationState implements StateInterface {

  /**
   * The vanilla state object from the Workflow module.
   *
   * @var \Drupal\workflows\StateInterface
   */
  protected $state;

  /**
   * If entities should be published if in this state.
   *
   * @var bool
   */
  protected $published;

  /**
   * If entities should be the default revision if in this state.
   *
   * @var bool
   */
  protected $defaultRevision;

  public function __construct(StateInterface $state, $published = FALSE, $default_revision = FALSE) {
    $this->state = $state;
    $this->published = $published;
    $this->defaultRevision = $default_revision;
  }

  /**
   * Determines if entities should be published if in this state.
   *
   * @return bool
   *   TRUE if entities should be published if in this state, FALSE if not.
   */
  public function isPublishedState() {
    return $this->published;
  }

  /**
   * Determines if entities should be the default revision if in this state.
   *
   * @return bool
   *   TRUE if entities should be the default revision if in this state, FALSE
   *   if not.
   */
  public function isDefaultRevisionState() {
    return $this->defaultRevision;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->state->id();
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->state->label();
  }

  /**
   * {@inheritdoc}
   */
  public function weight() {
    return $this->state->weight();
  }

  /**
   * {@inheritdoc}
   */
  public function canTransitionTo($to_state_id) {
    return $this->state->canTransitionTo($to_state_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionTo($to_state_id) {
    return $this->state->getTransitionTo($to_state_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions() {
    return $this->state->getTransitions();
  }

}
