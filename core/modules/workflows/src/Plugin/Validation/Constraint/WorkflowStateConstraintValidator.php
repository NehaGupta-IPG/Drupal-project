<?php

declare(strict_types=1);

namespace Drupal\workflows\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\EntityWorkflowTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates workflow state transitions.
 *
 * Performs three sequential checks with distinct violation messages:
 * 1. The target state must exist in the workflow.
 * 2. A transition must exist from the original state to the target state.
 * 3. The current user must have access to perform the transition.
 */
class WorkflowStateConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    /** @var \Drupal\workflows\Plugin\Field\FieldType\WorkflowStateItem $value */
    $workflow = $value->getWorkflow();
    if (!$workflow) {
      $this->context->addViolation($constraint->message, [
        '%state' => $value->getStateId() ?? '',
      ]);
      return;
    }

    $type_plugin = $workflow->getTypePlugin();
    $current_value = $value->getStateId();
    $original_value = $value->getOriginalStateId();

    // The state must exist in the workflow.
    if ($current_value !== NULL && !$type_plugin->hasState($current_value)) {
      $this->context->addViolation($constraint->message, [
        '%state' => $current_value,
      ]);
      return;
    }

    // No state change, or no original state (e.g., the field was added to an
    // entity type with existing data) — nothing more to check.
    if ($current_value === $original_value || $original_value === NULL) {
      return;
    }

    $params = [
      '%from' => $type_plugin->getState($original_value)->label(),
      '%to' => $type_plugin->getState($current_value)->label(),
    ];

    // The transition must exist.
    if (!$type_plugin->hasTransitionFromStateToState($original_value, $current_value)) {
      $this->context->addViolation($constraint->invalidTransitionMessage, $params);
      return;
    }

    // The user must have access to the transition.
    $transition = $value->getCurrentTransition();
    assert($type_plugin instanceof EntityWorkflowTypeInterface);
    $access = $type_plugin->checkTransitionAccess(
      $transition,
      $this->currentUser,
      $value->getEntity(),
      $value->getFieldDefinition()->getName(),
    );
    if (!$access->isAllowed()) {
      $this->context->addViolation($constraint->invalidTransitionAccessMessage, $params);
    }
  }

}
