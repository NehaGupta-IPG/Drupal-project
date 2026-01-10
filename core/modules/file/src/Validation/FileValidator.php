<?php

namespace Drupal\file\Validation;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Validation\ConstraintManager;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a class for file validation.
 */
class FileValidator implements FileValidatorInterface {

  public function __construct(
    #[Autowire(service: 'file.recursive_validator')]
    protected ValidatorInterface $validator,
    #[Autowire(service: 'validation.constraint')]
    protected ConstraintManager $constraintManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate(FileInterface $file, array $validators): ConstraintViolationListInterface {
    $constraints = [];
    foreach ($validators as $validator => $options) {
      // Create the constraint.
      // Options are an associative array of constraint properties and values.
      $constraints[] = $this->constraintManager->create($validator, $options);
    }

    // Get the typed data.
    $fileTypedData = $file->getTypedData();

    $violations = $this->validator->validate($fileTypedData, $constraints);

    $this->eventDispatcher->dispatch(new FileValidationEvent($file, $violations));

    // Always check the insecure upload constraint.
    if (count($violations) === 0) {
      $insecureUploadConstraint = $this->constraintManager->create('FileExtensionSecure', []);
      $violations = $this->validator->validate($fileTypedData, $insecureUploadConstraint);
    }

    return $violations;
  }

}
