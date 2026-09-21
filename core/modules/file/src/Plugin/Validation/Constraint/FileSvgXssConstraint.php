<?php

declare(strict_types=1);

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * SVG XSS filter constraint.
 */
#[Constraint(
  id: 'FileSvgXss',
  label: new TranslatableMarkup('SVG XSS filter', [], ['context' => 'Validation']),
  type: 'file'
)]
class FileSvgXssConstraint extends SymfonyConstraint {

  public function __construct(
    public string $message = 'For security reasons, your upload has been rejected.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
