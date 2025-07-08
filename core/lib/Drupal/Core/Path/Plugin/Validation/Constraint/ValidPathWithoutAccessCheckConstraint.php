<?php

namespace Drupal\Core\Path\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for valid system paths (without access check).
 */
#[Constraint(
  id: 'ValidPathWithoutAccessCheck',
  label: new TranslatableMarkup('Valid path (no access check).', [], ['context' => 'Validation'])
)]
class ValidPathWithoutAccessCheckConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = "The path '%link_path' is invalid.";

}
