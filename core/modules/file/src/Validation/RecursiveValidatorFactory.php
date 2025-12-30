<?php

namespace Drupal\file\Validation;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Validation\ExecutionContextFactory;
use Drupal\Core\TypedData\Validation\RecursiveValidator;
use Drupal\Core\Validation\ConstraintValidatorFactory;
use Drupal\Core\Validation\DrupalTranslator;

/**
 * Factory for creating a new RecursiveValidator.
 */
class RecursiveValidatorFactory {

  public function __construct(
    protected ClassResolverInterface $classResolver,
    protected TypedDataManagerInterface $typedDataManager,
  ) {}

  /**
   * Creates a new RecursiveValidator.
   *
   * @return \Drupal\Core\TypedData\Validation\RecursiveValidator
   *   The validator.
   */
  public function createValidator(): RecursiveValidator {
    return new RecursiveValidator(
      new ExecutionContextFactory(new DrupalTranslator()),
      new ConstraintValidatorFactory($this->classResolver),
      $this->typedDataManager,
    );
  }

}
