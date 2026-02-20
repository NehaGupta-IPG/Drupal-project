<?php

declare(strict_types=1);

namespace Drupal\workflows\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\AllowedValuesConstraint;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\StateInterface;
use Drupal\workflows\EntityWorkflowTypeInterface;
use Drupal\workflows\TransitionInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\workflows\WorkflowStateFieldItemList;
use Drupal\workflows\WorkflowStateItemInterface;

/**
 * Plugin implementation of the 'workflow_state' field type.
 */
#[FieldType(
  id: "workflow_state",
  label: new TranslatableMarkup("Workflow state"),
  description: new TranslatableMarkup("Stores the current workflow state."),
  default_widget: "options_select",
  default_formatter: "list_default",
  list_class: WorkflowStateFieldItemList::class,
  cardinality: 1,
)]
class WorkflowStateItem extends FieldItemBase implements WorkflowStateItemInterface, OptionsProviderInterface {

  /**
   * The original value, used to validate state changes.
   */
  protected ?string $originalValue = NULL;

  /**
   * The transition to apply.
   *
   * When applyTransition() is called, the transition is stored here to ensure
   * the correct transition is referenced in pre/post save hooks, rather than
   * guessing via state-to-state lookup.
   */
  protected ?TransitionInterface $transitionToApply = NULL;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('State'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    // Replace the 'AllowedValuesConstraint' constraint with the
    // 'WorkflowState' one. The standard AllowedValuesConstraint from
    // OptionsProviderInterface doesn't understand workflow transitions.
    foreach ($constraints as $key => $constraint) {
      if ($constraint instanceof AllowedValuesConstraint) {
        unset($constraints[$key]);
      }
    }
    $manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints[] = $manager->create('WorkflowState', []);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'workflow' => '',
      'workflow_callback' => '',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];
    // Allow the workflow to be changed if it's not determined by a callback.
    if (!$this->getSetting('workflow_callback')) {
      $options = [];
      foreach (Workflow::loadMultiple() as $workflow) {
        if (!$workflow->getTypePlugin() instanceof EntityWorkflowTypeInterface) {
          continue;
        }
        $options[$workflow->id()] = $workflow->label();
      }

      $element['workflow'] = [
        '#type' => 'select',
        '#title' => $this->t('Workflow'),
        '#options' => $options,
        '#default_value' => $this->getSetting('workflow'),
        '#disabled' => $has_data,
        '#required' => TRUE,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    if ($workflow = $this->getWorkflow()) {
      $initial_state = $workflow->getTypePlugin()->getInitialState();
      $this->setValue(['value' => $initial_state->id()], $notify);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if ($this->originalValue === NULL) {
      // If no array is given, then the method received just the state value.
      if (isset($values) && !is_array($values)) {
        $values = ['value' => $values];
      }
      // Track the original field value to allow isValid() to validate changes
      // and to react to transitions.
      $this->originalValue = $values['value'] ?? NULL;
    }
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL) {
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      // The workflow is not known yet, the field is probably being created.
      return [];
    }
    return array_map(
      fn(StateInterface $state) => $state->label(),
      $workflow->getTypePlugin()->getStates(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    return array_keys($this->getSettableOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL) {
    // $this->value is unpopulated due to https://www.drupal.org/node/2629932
    $field_name = $this->getFieldDefinition()->getName();
    if (!$this->getEntity()->hasField($field_name)) {
      return [];
    }
    $value = $this->getEntity()->get($field_name)->value;
    $allowed_states = $this->getAllowedStates($value, $account);

    return array_map(
      fn(StateInterface $state) => $state->label(),
      $allowed_states,
    );
  }

  /**
   * Gets the next allowed states for the given field value.
   *
   * This includes the current state (so the user can "keep" the current
   * state in a select widget) plus all states reachable via transitions.
   *
   * @param string|null $value
   *   The field value, representing the state ID.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account to check access for, or NULL for the current user.
   *
   * @return \Drupal\workflows\StateInterface[]
   *   The allowed states.
   */
  protected function getAllowedStates(?string $value, ?AccountInterface $account = NULL): array {
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      // The workflow is not known yet, the field is probably being created.
      return [];
    }

    $allowed_states = [];
    $type_plugin = $workflow->getTypePlugin();

    if (!empty($value) && $type_plugin->hasState($value)) {
      // Include the current state so the user can "keep" the current state.
      $allowed_states[$value] = $type_plugin->getState($value);

      // Add all states reachable via transitions from the current state.
      foreach ($type_plugin->getState($value)->getTransitions() as $transition) {
        if (!$this->hasTransitionAccess($transition, $account)) {
          continue;
        }
        $state = $transition->to();
        $allowed_states[$state->id()] = $state;
      }
    }

    return $allowed_states;
  }

  /**
   * Resolves the workflow ID from field settings.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   The entity, or NULL in static contexts.
   *
   * @return string|null
   *   The workflow ID, or NULL if it could not be determined.
   */
  public static function resolveWorkflowId(FieldDefinitionInterface $field_definition, ?FieldableEntityInterface $entity = NULL): ?string {
    $storage_definition = $field_definition->getFieldStorageDefinition();
    if ($callback = $storage_definition->getSetting('workflow_callback')) {
      $workflow_id = call_user_func($callback, $field_definition, $entity);
    }
    else {
      $workflow_id = $storage_definition->getSetting('workflow');
    }

    return !empty($workflow_id) ? $workflow_id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): ?WorkflowInterface {
    $workflow_id = static::resolveWorkflowId($this->getFieldDefinition(), $this->getEntity());
    if (!$workflow_id) {
      return NULL;
    }

    $workflow = Workflow::load($workflow_id);
    if ($workflow && !$workflow->getTypePlugin() instanceof EntityWorkflowTypeInterface) {
      throw new \LogicException(sprintf(
        'The workflow type plugin "%s" must implement EntityWorkflowTypeInterface to be used with the workflow_state field type.',
        $workflow->getTypePlugin()->getPluginId(),
      ));
    }

    return $workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateId(): ?string {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateLabel(): string {
    return $this->resolveStateLabel($this->getStateId());
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalStateId(): ?string {
    return $this->originalValue;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalStateLabel(): string {
    return $this->resolveStateLabel($this->getOriginalStateId());
  }

  /**
   * Gets the label for the given state ID.
   *
   * @param string|null $state_id
   *   The state ID.
   *
   * @return string
   *   The state label, or an empty string if unknown.
   */
  protected function resolveStateLabel(?string $state_id): string {
    $workflow = $this->getWorkflow();
    if (!$workflow || !$state_id || !$workflow->getTypePlugin()->hasState($state_id)) {
      return '';
    }
    return $workflow->getTypePlugin()->getState($state_id)->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions(?AccountInterface $account = NULL): array {
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      return [];
    }
    $type_plugin = $workflow->getTypePlugin();
    if (!$this->value || !$type_plugin->hasState($this->value)) {
      return [];
    }

    return array_filter(
      $type_plugin->getState($this->value)->getTransitions(),
      fn(TransitionInterface $transition) => $this->hasTransitionAccess($transition, $account),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionAllowed(TransitionInterface $transition, ?AccountInterface $account = NULL): bool {
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      return FALSE;
    }

    // Verify the transition can originate from the current state.
    foreach ($transition->from() as $from_state) {
      if ($from_state->id() === $this->value) {
        return $this->hasTransitionAccess($transition, $account);
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function applyTransition(TransitionInterface $transition): void {
    if (!$this->isTransitionAllowed($transition)) {
      throw new \InvalidArgumentException(sprintf('The transition "%s" is not allowed. (Current state: "%s".)', $transition->id(), $this->getStateId()));
    }
    // Store the transition to apply, to ensure we're applying the requested
    // transition instead of guessing based on the original state.
    $this->transitionToApply = $transition;
    $this->setValue(['value' => $transition->to()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function applyTransitionById(string $transition_id): void {
    $workflow = $this->getWorkflow();
    if (!$workflow || !$workflow->getTypePlugin()->hasTransition($transition_id)) {
      throw new \InvalidArgumentException(sprintf('Unknown transition ID "%s".', $transition_id));
    }
    $transition = $workflow->getTypePlugin()->getTransition($transition_id);
    $this->applyTransition($transition);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    $this->invokeTransitionHook('pre_transition');
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $this->invokeTransitionHook('post_transition');
    $this->originalValue = $this->value;
    $this->transitionToApply = NULL;
    // No need for the entity storage to re-save field values.
    return FALSE;
  }

  /**
   * Invokes a transition hook for the given phase.
   *
   * @param string $phase
   *   Either 'pre_transition' or 'post_transition'.
   */
  protected function invokeTransitionHook(string $phase): void {
    $transition = $this->getCurrentTransition();
    if ($transition) {
      \Drupal::moduleHandler()->invokeAll('workflows_' . $phase, [
        $this->getWorkflow(),
        $transition,
        $this->getEntity(),
        $this->getFieldDefinition()->getName(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTransition(): ?TransitionInterface {
    if ($this->transitionToApply) {
      return $this->transitionToApply;
    }
    if ($this->value === $this->originalValue) {
      return NULL;
    }
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      return NULL;
    }
    $type_plugin = $workflow->getTypePlugin();
    if ($type_plugin->hasTransitionFromStateToState($this->originalValue, $this->value)) {
      return $type_plugin->getTransitionFromStateToState($this->originalValue, $this->value);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(): bool {
    $workflow = $this->getWorkflow();
    if (!$workflow) {
      return FALSE;
    }

    // If a transition is needed, validate that one exists and the current
    // user has access.
    $transition = $this->getCurrentTransition();
    if ($transition !== NULL) {
      return $this->hasTransitionAccess($transition);
    }

    // No state change — simply validate that the current state exists.
    return $this->value !== NULL && $workflow->getTypePlugin()->hasState($this->value);
  }

  /**
   * Checks whether a user has access to perform the given transition.
   *
   * @param \Drupal\workflows\TransitionInterface $transition
   *   The transition to check.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account to check access for, or NULL for the current user.
   *
   * @return bool
   *   TRUE if the user has access, FALSE otherwise.
   */
  protected function hasTransitionAccess(TransitionInterface $transition, ?AccountInterface $account = NULL): bool {
    $type_plugin = $this->getWorkflow()->getTypePlugin();
    assert($type_plugin instanceof EntityWorkflowTypeInterface);
    return $type_plugin->checkTransitionAccess(
      $transition,
      $account ?? \Drupal::currentUser(),
      $this->getEntity(),
      $this->getFieldDefinition()->getName(),
    )->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition) {
    $dependencies = [];

    $workflow_id = static::resolveWorkflowId($field_definition);
    if ($workflow_id && ($workflow = Workflow::load($workflow_id))) {
      $dependencies['config'][] = $workflow->getConfigDependencyName();
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $workflow_id = static::resolveWorkflowId($field_definition);
    if (!$workflow_id) {
      return [];
    }

    $workflow = Workflow::load($workflow_id);
    if (!$workflow) {
      return [];
    }

    $states = $workflow->getTypePlugin()->getStates();
    if (empty($states)) {
      return [];
    }

    // Select states that have at least one outgoing transition, so the
    // sample entity can be transitioned further.
    $candidate_states = [];
    foreach ($states as $state_id => $state) {
      if (!empty($state->getTransitions())) {
        $candidate_states[$state_id] = $state;
      }
    }

    // Fall back to all states if none have transitions.
    if (empty($candidate_states)) {
      $candidate_states = $states;
    }

    $random_state_id = array_rand($candidate_states);
    return ['value' => $random_state_id];
  }

}
