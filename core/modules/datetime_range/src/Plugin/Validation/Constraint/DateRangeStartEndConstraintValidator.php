<?php

namespace Drupal\datetime_range\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for DateRange items to ensure the format is correct.
 */
class DateRangeStartEndConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    if (!isset($item)) {
      return;
    }

    /** @var \Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem $item */
    $value = $item->getValue()['value'];
    $end_value = $item->getValue()['end_value'];

    // We cannot get the computed values from the item at this point, so we
    // have to use string comparison. This may give false error messages, but
    // the format constraints will also fail and provide a proper message for
    // the user.
    if (is_string($value) && is_string($end_value)) {
      if ($value > $end_value) {
        $this->context->addViolation($constraint->badStartEnd, [
          '@value' => $value,
          '@end_value' => $end_value,
        ]);
      }
    }
  }

}
