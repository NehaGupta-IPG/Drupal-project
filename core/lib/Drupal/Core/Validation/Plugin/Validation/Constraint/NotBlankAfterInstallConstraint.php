<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Supports validation after site is installed.
 */
#[Constraint(
  id: 'NotBlankAfterInstall',
  label: new TranslatableMarkup('Not blank after install', [], ['context' => 'Validation'])
)]
class NotBlankAfterInstallConstraint extends SymfonyConstraint {

  /**
   * The error message.
   */
  public string $message = 'The %field field cannot be blank after installation.';

}
