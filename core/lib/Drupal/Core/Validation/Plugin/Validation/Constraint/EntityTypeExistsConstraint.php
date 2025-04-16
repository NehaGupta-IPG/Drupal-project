<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * EntityTypeExists constraint.
 */
#[Constraint(
  id: 'EntityTypeExists',
  label: new TranslatableMarkup('Entity type exists', [], ['context' => 'Validation']),
  type: FALSE
)]
class EntityTypeExistsConstraint extends SymfonyConstraint {

  /**
   * The required interface that the entity type must implement.
   */
  public ?string $interface;

  /**
   * The error message if validation fails.
   */
  public string $message = "The '@entity_type_id' entity type does not exist.";

  /**
   * The error message if validation of interface fails.
   *
   * @var string
   */
  public string $interfaceMissingMessage = 'The @entity_type_id entity type does not implement the @interface interface.';

  /**
   * The error message if the value is not a string.
   *
   * @var string
   */
  public string $valueIsNoStringMessage = 'The value is not a string, cannot validate if entity type exists.';

}
