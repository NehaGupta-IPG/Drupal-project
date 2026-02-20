<?php

declare(strict_types=1);

namespace Drupal\workflows\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Ensures the validity of the workflow state.
 *
 * The state must exist on the used workflow, and, if changed, the transition
 * must be allowed.
 */
#[Constraint(
  id: 'WorkflowState',
  label: new TranslatableMarkup('Valid workflow state', [], ['context' => 'Validation'])
)]
class WorkflowStateConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $message = "The state %state is not valid.",
    public string $invalidTransitionMessage = "The transition from %from to %to is not allowed.",
    public string $invalidTransitionAccessMessage = "You do not have access to transition from %from to %to.",
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
