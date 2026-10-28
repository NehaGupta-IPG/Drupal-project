<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validate multiple values on field to be unique.
 */
class MultipleUniqueValueConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    $fieldValue = $value;
    $delta = '';
    if ($value instanceof FieldItemListInterface) {
      $fieldValue = $value->getString();
      $delta = $value->getName();
    }
    elseif (is_string($fieldValue)) {
      $fieldValue = array_map('trim', explode("\n", trim($fieldValue)));
    }
    $fieldValueAccount = array_count_values($fieldValue);
    foreach ($fieldValueAccount as $count) {
      if ($count > 1) {
        $this->context->buildViolation($constraint->message)
          ->atPath($delta)
          ->addViolation();
      }
    }
  }

}
