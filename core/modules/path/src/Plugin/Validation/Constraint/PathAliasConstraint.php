<?php

namespace Drupal\path\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for path alias changes.
 */
#[Constraint(
  id: 'PathAlias',
  label: new TranslatableMarkup('Path alias.', [], ['context' => 'Validation'])
)]
class PathAliasConstraint extends SymfonyConstraint {

  public function __construct(
    public $message = 'You can only change the URL alias for the <em>published</em> version of this content.',
    public $pidMessage = 'The path alias ID does not match an alias for this content.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
