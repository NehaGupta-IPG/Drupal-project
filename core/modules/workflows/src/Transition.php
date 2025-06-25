<?php

namespace Drupal\workflows;

/**
 * A transition value object that describes the transition between states.
 */
class Transition implements TransitionInterface {

  /**
   * The workflow that this transition is attached to.
   *
   * @var \Drupal\workflows\WorkflowTypeInterface
   */
  protected $workflow;

  /**
   * The transition's from state IDs.
   *
   * @var string[]
   */
  protected $fromStateIds;

  /**
   * The transition's to state ID.
   *
   * @var string
   */
  protected $toStateId;

  /**
   * Transition constructor.
   *
   * @param \Drupal\workflows\WorkflowTypeInterface $workflow
   *   The workflow the state is attached to.
   * @param string $id
   *   The transition's ID.
   * @param string $label
   *   The transition's label.
   * @param array $from_state_ids
   *   A list of from state IDs.
   * @param string $to_state_id
   *   The to state ID.
   * @param int $weight
   *   (optional) The transition's weight. Defaults to 0.
   */
  public function __construct(
    WorkflowTypeInterface $workflow,
    protected $id,
    protected $label,
    array $from_state_ids,
    $to_state_id,
    protected $weight = 0,
  ) {
    $this->workflow = $workflow;
    $this->fromStateIds = $from_state_ids;
    $this->toStateId = $to_state_id;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function from() {
    return $this->workflow->getStates($this->fromStateIds);
  }

  /**
   * {@inheritdoc}
   */
  public function to() {
    return $this->workflow->getState($this->toStateId);
  }

  /**
   * {@inheritdoc}
   */
  public function weight() {
    return $this->weight;
  }

}
