<?php

declare(strict_types=1);

namespace Drupal\datetime_range\Plugin\Validation\Constraint;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for DateRange items to ensure the format is correct.
 */
class DateRangeFormatConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    $stop = FALSE;

    $value = $item->getValue()['value'];
    $end_value = $item->getValue()['end_value'];

    if (!is_string($value)) {
      $this->context->addViolation($constraint->badStartType);
      $stop = TRUE;
    }
    if (!is_string($end_value)) {
      $this->context->addViolation($constraint->badEndType);
      $stop = TRUE;
    }

    if ($stop) {
      return;
    }

    $datetime_type = $item->getFieldDefinition()->getSetting('datetime_type');
    if ($datetime_type === DateTimeItem::DATETIME_TYPE_DATE) {
      $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
    }
    else {
      $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    }

    $start_date = NULL;
    try {
      $start_date = DateTimePlus::createFromFormat($format, $value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    }
    catch (\InvalidArgumentException) {
      $this->context->addViolation($constraint->badStartFormat, [
        '@value' => $value,
        '@format' => $format,
      ]);
      $stop = TRUE;
    }
    catch (\UnexpectedValueException) {
      $this->context->addViolation($constraint->badStartValue, [
        '@value' => $value,
        '@format' => $format,
      ]);
      $stop = TRUE;
    }

    $end_date = NULL;
    try {
      $end_date = DateTimePlus::createFromFormat($format, $end_value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    }
    catch (\InvalidArgumentException) {
      $this->context->addViolation($constraint->badEndFormat, [
        '@end_value' => $end_value,
        '@format' => $format,
      ]);
      $stop = TRUE;
    }
    catch (\UnexpectedValueException) {
      $this->context->addViolation($constraint->badEndValue, [
        '@end_value' => $end_value,
        '@format' => $format,
      ]);
      $stop = TRUE;
    }

    if ($stop) {
      return;
    }

    if ($start_date === NULL || $start_date->hasErrors()) {
      $this->context->addViolation($constraint->badStartFormat, [
        '@value' => $value,
        '@format' => $format,
      ]);
    }

    if ($end_date === NULL || $end_date->hasErrors()) {
      $this->context->addViolation($constraint->badEndFormat, [
        '@end_value' => $end_value,
        '@format' => $format,
      ]);
    }
  }

}
