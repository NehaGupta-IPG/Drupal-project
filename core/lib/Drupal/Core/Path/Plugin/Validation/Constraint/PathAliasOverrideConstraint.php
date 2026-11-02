<?php

namespace Drupal\Core\Path\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'PathAliasOverride',
  label: new TranslatableMarkup('Alias matches a system path.', [], ['context' => 'Validation'])
)]
class PathAliasOverrideConstraint extends SymfonyConstraint {

  public $message = 'The alias "%alias" matches an existing system path.';

}
