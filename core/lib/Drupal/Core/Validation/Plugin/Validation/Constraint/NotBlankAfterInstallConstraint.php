<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * NotBlankAfterInstallConstraint constraint.
 *
 * Extends the Symfony constraint to allow blank during install.
 */
#[Constraint(
  id: 'NotBlankAfterInstallConstraint',
  label: new TranslatableMarkup('Not blank after install', [], ['context' => 'Validation']),
  type: FALSE
)]
class NotBlankAfterInstallConstraint extends NotBlank {
}
