<?php

declare(strict_types=1);

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * File extension constraint.
 */
#[Constraint(
  id: 'FileUri',
  label: new TranslatableMarkup('File Extension', [], ['context' => 'Validation']),
  type: 'file'
)]
class FileUriConstraint extends SymfonyConstraint {

  /**
   * Message indicating that the file URI is not valid.
   */
  public string $message = 'The file URI is not valid.';

  /**
   * Message indicating that only existing files are permitted.
   */
  public string $messagePath = 'Only files that exist in the file system are allowed.';

  /**
   * Message indicating that the file does not exist in the file system.
   */
  public string $messageExists = 'The file does not exist in the file system.';

  /**
   * Flag indicating whether the file is required to exist.
   */
  public bool $mustExist = TRUE;

}
