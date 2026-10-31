<?php

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the RestrictZeroByteFileConstraint.
 */
class FileZeroByteConstraintValidator extends BaseFileConstraintValidator implements ContainerInjectionInterface {

  /**
   * Creates a new RestrictZeroByteFileConstraintValidator.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    $file = $this->assertValueIsFile($value);
    if (!$constraint instanceof FileZeroByteConstraint) {
      throw new UnexpectedTypeException($constraint, FileZeroByteConstraint::class);
    }

    if ($file->getSize() == 0) {
      $this->context->addViolation($constraint->zeroByteFileMessage);
    }
  }

}
