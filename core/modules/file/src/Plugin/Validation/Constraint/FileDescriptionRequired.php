<?php

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Constrains the existence of a file description if it has been configured.
 */
#[Constraint(
  id: 'FileDescriptionRequired',
  label: new TranslatableMarkup('File required description', [], ['context' => 'Validation']),
)]
class FileDescriptionRequired extends SymfonyConstraint {

  /**
   * Constraint violation message template.
   *
   * @var string
   */
  public $message = 'The @name field description is required.';

}
