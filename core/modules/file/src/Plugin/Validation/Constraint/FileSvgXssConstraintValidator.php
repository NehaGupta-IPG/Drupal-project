<?php

declare(strict_types=1);

namespace Drupal\file\Plugin\Validation\Constraint;

use enshrined\svgSanitize\Sanitizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for the FileSvgXssConstraint.
 */
class FileSvgXssConstraintValidator extends BaseFileConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    $file = $this->assertValueIsFile($value);
    if (!$constraint instanceof FileSvgXssConstraint) {
      throw new UnexpectedTypeException($constraint, FileSvgXssConstraint::class);
    }

    if ($file->getMimeType() != 'image/svg+xml') {
      return;
    }

    $sanitizer = new Sanitizer();
    $sanitizer->sanitize(file_get_contents($file->getFileUri()));
    if (!empty($sanitizer->getXmlIssues())) {
      $this->context->addViolation($constraint->message);
    }
  }

}
