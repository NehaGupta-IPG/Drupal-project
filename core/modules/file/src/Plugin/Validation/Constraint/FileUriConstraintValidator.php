<?php

declare(strict_types=1);

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Validation\ConstraintManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the file URI.
 *
 * Validates if the file URI is valid. Then validated if the file exists in the
 * file system.
 */
class FileUriConstraintValidator extends ConstraintValidator {

  use AutowireTrait;

  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
    protected readonly ConstraintManager $constraintManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof FileUriConstraint) {
      throw new UnexpectedTypeException($constraint, FileExtensionConstraint::class);
    }

    $scheme = $this->streamWrapperManager::getScheme($value);

    // Since no scheme is involved we assume its a real file.
    if ($scheme === FALSE) {
      // @see FileUrlGenerator

      $basePath = '/var/www'; // @todo get the base path from a helper somewhere.
      $value = $basePath . '/' . $value;

      $constraint = $this->constraintManager->create('File', []);

      $this->context->getValidator()
        ->validate($value, $constraint);
      return;
    }

    $valid_uri = $this->streamWrapperManager->isValidUri($value);
    if ($valid_uri === FALSE) {
      $this->context
        ->buildViolation($constraint->message, ['file_uri' => $value])
        ->addViolation();
      return;
    }

    $wrapper = $this->streamWrapperManager->getViaUri($value);
    $real_path = $wrapper->realpath();

    if (!is_string($real_path)) {
      $this->context->buildViolation($constraint->messagePath, ['file_uri' => $value])
        ->addViolation();
      return;
    }

    if ($constraint->mustExist === TRUE) {
      if (file_exists($real_path) !== TRUE || !is_file($real_path)) {
        $this->context
          ->buildViolation($constraint->messageExists, ['file_uri' => $value])
          ->addViolation();
      }
    }
  }

}
