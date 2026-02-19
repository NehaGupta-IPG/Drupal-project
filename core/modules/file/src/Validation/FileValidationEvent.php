<?php

namespace Drupal\file\Validation;

use Drupal\file\FileInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event for file validations.
 */
class FileValidationEvent extends Event {

  /**
   * Creates a new FileValidationEvent.
   *
   * @param FileInterface $file
   *   The file.
   * @param ConstraintViolationListInterface $violations
   *   The violations.
   */
  public function __construct(
    public readonly FileInterface $file,
    public readonly ConstraintViolationListInterface $violations,
  ) {}

}
