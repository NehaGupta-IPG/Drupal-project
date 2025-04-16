<?php

declare(strict_types = 1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\TypeResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the StringParts constraint.
 */
class StringPartsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!is_string($value)) {
      throw new UnexpectedTypeException($value, 'string');
    }

    $resolved_parts = array_map(
      fn (string $expression): mixed => TypeResolver::resolveExpression($expression, $this->context->getObject()),
      $constraint->parts
    );

    // Verify the required parts are present; if not, that's a logical error in
    // the config schema, not in concrete config.
    $missing_properties = array_intersect($constraint->parts, $resolved_parts);
    if (!empty($missing_properties)) {
      throw new \LogicException(sprintf('This validation constraint is configured to inspect the properties %s, but some do not exist: %s.',
        implode(', ', $constraint->parts),
        implode(', ', $missing_properties)
      ));
    }

    // Retrieve the parts of the expected string.
    $expected_string_parts = [];
    foreach ($constraint->parts as $index => $part) {
      $part_value = $resolved_parts[$index];
      if (!is_string($part_value)) {
        throw new \LogicException(sprintf('The "%s" property does not contain a string, but a %s: "%s".', $part, gettype($part_value), (string) $part_value));
      }
      $expected_string_parts[] = $part_value;
    }
    $expected_string = implode($constraint->separator, $expected_string_parts);

    if ($expected_string !== $value) {
      $expected_format = implode(
        $constraint->separator,
        array_map(function (string $v) {
          return sprintf('<%s>', $v);
        }, $constraint->parts)
      );
      $this->context->addViolation($constraint->message, [
        '@value' => $value,
        '@expected_string' => $expected_string,
        '@expected_format' => $expected_format,
      ]);
    }
  }

}
