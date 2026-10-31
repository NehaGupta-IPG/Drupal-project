<?php

declare(strict_types = 1);

namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the ExistsIn constraint.
 */
class ExistsInConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {
    if (!$constraint instanceof ExistsInConstraint) {
      throw new UnexpectedTypeException($constraint, ExistsInConstraint::class);
    }

    $this_property = $this->context->getObject();
    assert($this_property instanceof TypedDataInterface);
    $choices_raw = self::find($constraint->selector, $this_property)->getValue();

    $choices = match($constraint->which) {
      'keys' => array_keys($choices_raw),
      'values' => array_values($choices_raw),
    };

    if (!in_array($value, $choices)) {
      $this->context->addViolation($constraint->message, [
        '@value' => $value,
        '@choices' => implode(', ', $choices),
      ]);
    }
  }

  /**
   * Resolves an expression that selects other typed data in the same root.
   *
   * The expression may contain the following special strings:
   * - '%parent', to reference the parent element.
   *
   * There may be nested configuration keys separated by dots or more complex
   * patterns like '%parent.name' which references the 'name' value of the
   * parent element.
   *
   * Example expressions:
   * - 'name.subkey', indicates a nested value of the current element.
   * - '%parent.name', will be replaced by the 'name' value of the parent.
   * - '%parent.%parent.foo', will be replaced by the 'foo' value of the
   *   parent's parent.
   *
   * @param string $expression
   *   Expression to be resolved.
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   Configuration data for the element.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The selected typed data elsewhere in the same config object.
   *
   * @see \Drupal\Core\Config\Schema\TypeResolver::resolveExpression()
   */
  private static function find(string $expression, TypedDataInterface $data): TypedDataInterface {
    $parts = explode('.', $expression);
    // Process each value part, one at a time.
    while ($name = array_shift($parts)) {
      // Either go up a level.
      if ($name === '%parent') {
        if ($data->getRoot() === $data) {
          throw new \LogicException('Cannot get the parent of a config object (a root).');
        }
        $data = $data->getParent();
      }
      // Or go down a level.
      else {
        if (!$data instanceof ArrayElement) {
          throw new \LogicException(sprintf('This is not an array element, so cannot get `%s`.', $name));
        }
        if (!array_key_exists($name, $data->getElements())) {
          throw new \LogicException(sprintf('`%s` does not exist at `%s`.', $name, $data->getPropertyPath()));
        }
        $data = $data->getElements()[$name];
      }
    }
    return $data;
  }

}
