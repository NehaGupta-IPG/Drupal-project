<?php

declare(strict_types = 1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that a value in this config matches a value in other config.
 *
 * @Constraint(
 *   id = "MatchesOtherConfigValue",
 *   label = @Translation("Value must match value in other config", context = "Validation")
 * )
 */
class MatchesOtherConfigValueConstraint extends Constraint {

  /**
   * The error message if the string does not match.
   *
   * @var string
   */
  public string $message = "Expected this to match the value in the '@other_config_name' config at the '@other_config_property_path' property: '@expected_value' was expected, not '@actual_value'.";

  /**
   * The config prefix to use.
   *
   * @var string
   */
  public string $prefix;

  /**
   * One or more expressions that resolve to the config entity ID parts.
   *
   * @var string|string[]
   * @see \Drupal\Core\Config\Schema\TypeResolver::resolveExpression()
   */
  public string|array $id;

  /**
   * The property path.
   *
   * @var string
   */
  public string $propertyPath;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['prefix', 'id', 'propertyPath'];
  }

}
