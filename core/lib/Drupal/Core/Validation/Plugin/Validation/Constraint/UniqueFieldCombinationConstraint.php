<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a set of config entity fields has a unique combination.
 *
 * @Constraint(
 *   id = "UniqueFieldCombination",
 *   label = @Translation("Unique field combination constraint", context = "Validation"),
 * )
 */
class UniqueFieldCombinationConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'A @entity_type with this combination of values for the fields @field_name_list (%field_value_list) already exists.';

  /**
   * Fields which must have a unique combination among all entities of a type.
   *
   * @var string[]
   */
  public array $fields;

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): string {
    return 'fields';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['fields'];
  }

}
