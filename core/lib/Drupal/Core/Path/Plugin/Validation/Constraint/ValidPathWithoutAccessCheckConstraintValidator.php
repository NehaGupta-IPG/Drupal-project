<?php

namespace Drupal\Core\Path\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for validating system paths (without access check).
 */
class ValidPathWithoutAccessCheckConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Creates a new ValidPathWithoutAccessCheckConstraintValidator instance.
   *
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   */
  public function __construct(PathValidatorInterface $path_validator) {
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!isset($value)) {
      return;
    }

    $path = trim($value, '/');
    if (!(bool) $this->pathValidator->getUrlIfValidWithoutAccessCheck($path)) {
      $this->context->addViolation($constraint->message, [
        '%link_path' => $value,
      ]);
    }
  }

}
