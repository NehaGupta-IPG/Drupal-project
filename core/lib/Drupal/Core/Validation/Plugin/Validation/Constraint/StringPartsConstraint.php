<?php

declare(strict_types = 1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks a string consists of specific parts found in the parent mapping.
 *
 * @Constraint(
 *   id = "StringParts",
 *   label = @Translation("String consists of specific parts", context = "Validation")
 * )
 */
class StringPartsConstraint extends Constraint {

  /**
   * The error message if the string does not match.
   *
   * @var string
   */
  public string $message = "Expected '@expected_string', not '@value'. Format: '@expected_format'.";

  /**
   * The separator separating the parts.
   *
   * @var string
   */
  public string $separator;

  /**
   * The parent mapping's elements string values that should be used as parts.
   *
   * @var array
   */
  public array $parts;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['separator', 'parts'];
  }

}
