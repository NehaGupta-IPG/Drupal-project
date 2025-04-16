<?php

namespace Drupal\Core\Field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that the value overrides a base field that actually exists.
 *
 * @Constraint(
 *   id = "IsBaseField",
 *   label = @Translation("Is a base field", context = "Validation")
 * )
 */
class IsBaseFieldConstraint extends Constraint {

  /**
   * Tbe error message if validation fails.
   *
   * @var string
   */
  public string $message = "'@field_name' is not a base field of the @entity_type entity type.";

}
