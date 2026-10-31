<?php

namespace Drupal\file\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Zero Byte File constraint.
 *
 * @Constraint(
 *   id = "FileZeroByte",
 *   label = @Translation("File Zero Byte", context = "Validation"),
 *   type = "file"
 * )
 */
class FileZeroByteConstraint extends Constraint {

  /**
   * The message for when an empty file is uploaded.
   *
   * @var string
   */
  public string $zeroByteFileMessage = 'The file is zero bytes. Upload a new valid file.';

}
