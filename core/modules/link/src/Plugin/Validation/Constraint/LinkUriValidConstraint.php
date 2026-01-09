<?php

namespace Drupal\link\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Defines a validation constraint for invalid characters in the URI.
 */
#[Constraint(
  id: 'LinkUriValid',
  label: new TranslatableMarkup('No invalid characters in the URI field', [], ['context' => 'Validation'])
)]
class LinkUriValidConstraint extends SymfonyConstraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $message = "The path %value contains invalid characters.";

}
