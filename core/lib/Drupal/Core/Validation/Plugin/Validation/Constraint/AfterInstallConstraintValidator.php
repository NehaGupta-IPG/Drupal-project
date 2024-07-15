<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\SequentiallyValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * AfterInstallConstraintValidator constraint validator.
 *
 * Extends the symfony validator to check whether Drupal is installing.
 */
class AfterInstallConstraintValidator extends SequentiallyValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$constraint instanceof Sequentially) {
      throw new UnexpectedTypeException($constraint, Sequentially::class);
    }

    // During the installer this does not need to be validated.
    if (InstallerKernel::installationAttempted()) {
      return;
    }

    $context = $this->context;

    // Cannot use the validator supplied by the context because the validate
    // method only supports typed data and at this point we have raw values.
    /** @var \Symfony\Component\Validator\Validator\RecursiveValidator $validator */
    $validator = \Drupal::service('validation.basic_recursive_validator_factory')->createValidator();

    $validator = $validator->inContext($context);

    $originalCount = $validator->getViolations()->count();

    foreach ($constraint->constraints as $c) {
      if ($originalCount !== $validator->validate($value, $c)->getViolations()->count()) {
        break;
      }
    }
  }

}
