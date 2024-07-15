<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlankValidator;

/**
 * NotBlankAfterInstallConstraintValidator constraint validator.
 *
 * Extends the symfony validator to check whether Drupal is installing.
 */
class NotBlankAfterInstallConstraintValidator extends NotBlankValidator {

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
