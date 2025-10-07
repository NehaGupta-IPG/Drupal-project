<?php

declare(strict_types=1);

namespace Drupal\datetime_range\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for DateTime items to ensure the format is correct.
 */
#[Constraint(
  id: 'DateRangeStartEnd',
  label: new TranslatableMarkup('Daterange formats valid for Daterange type.', [], ['context' => 'Validation']),
)]
class DateRangeStartEndConstraint extends SymfonyConstraint {

  /**
   * Message for when the end value is before the start value.
   *
   * @var string
   */
  public string $badStartEnd = "The date range end value '@end_value' must be equal to or after the start value '@value'";

}
