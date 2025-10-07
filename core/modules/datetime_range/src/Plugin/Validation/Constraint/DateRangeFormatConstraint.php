<?php

declare(strict_types=1);

namespace Drupal\datetime_range\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Validation constraint for DateTime items to ensure the format is correct.
 */
#[Constraint(
  id: 'DateRangeFormat',
  label: new TranslatableMarkup('Daterange formats valid for Daterange type.', [], ['context' => 'Validation']),
)]
class DateRangeFormatConstraint extends SymfonyConstraint {

  /**
   * Message for when the start value isn't a string.
   *
   * @var string
   */
  public string $badStartType = "The date range start value must be a string.";

  /**
   * Message for when the end value isn't a string.
   *
   * @var string
   */
  public string $badEndType = "The date range end value must be a string.";

  /**
   * Message for when the start value isn't in the proper format.
   *
   * @var string
   */
  public string $badStartFormat = "The date range start value '@value' is invalid for the format '@format'";

  /**
   * Message for when the end value isn't in the proper format.
   *
   * @var string
   */
  public string $badEndFormat = "The date range end value '@end_value' is invalid for the format '@format'";

  /**
   * Message for when the start value did not parse properly.
   *
   * @var string
   */
  public string $badStartValue = "The date range start value '@value' did not parse properly for the format '@format'";

  /**
   * Message for when the end value did not parse properly.
   *
   * @var string
   */
  public string $badEndValue = "The date range end value '@end_value' did not parse properly for the format '@format'";

}
