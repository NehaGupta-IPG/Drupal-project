<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlankValidator;

/**
 * NotBlankAfterInstallConstraintValidator constraint validator.
 *
 * Overrides the symfony validator to check whether Drupal is installing.
 */
class NotBlankAfterInstallConstraintValidator extends NotBlankValidator {

  use TypedDataAwareValidatorTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    // During the installer this does not need to be validated.
    if (InstallerKernel::installationAttempted()) {
      return;
    }
    parent::validate($value, $constraint);
  }

}
