<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * IsCommentable constraint.
 */
#[Constraint(
  id: 'IsCommentable',
  label: new TranslatableMarkup('Is commentable', [], ['context' => 'Validation']),
  type: FALSE
)]
class IsCommentableConstraint extends SymfonyConstraint {

  /**
   * The error message if validation fails.
   */
  public string $message = "The '@entity_type_id' entity is not commentable";

  /**
   * The error message if the value is not a string.
   */
  public string $valueIsNoStringMessage = 'The value is not a string, cannot validate if entity is commentable.';

}
