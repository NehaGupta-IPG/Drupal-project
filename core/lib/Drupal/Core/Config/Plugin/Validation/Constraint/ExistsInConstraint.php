<?php

declare(strict_types = 1);

namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that a value in this config exists in a list in the same config.
 *
 * @Constraint(
 *   id = "ExistsIn",
 *   label = @Translation("Value must match value in a list in the same config", context = "Validation")
 * )
 */
class ExistsInConstraint extends Constraint {

  /**
   * The error message if the string does not match.
   *
   * @var string
   */
  public string $message = "'@value' is not a valid choice. Valid choices are: @choices.";

  /**
   * An expression to select a different property path in the same config.
   *
   * @var string
   */
  public string $selector;

  /**
   * Treat the values or the keys as the valid choices: "keys" or "values".
   *
   * @var string
   */
  public string|array $which;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions() {
    return ['selector', 'which'];
  }

}
