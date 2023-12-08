<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates a string follows a stream wrapper pattern.
 */
class StreamWrapperUriConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Creates a StreamWrapperUriConstraintValidator object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   */
  public function __construct(protected StreamWrapperManagerInterface $streamWrapperManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(StreamWrapperManagerInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {
    if (!is_string($value)) {
      throw new UnexpectedTypeException($value, 'string');
    }
    if ($this->streamWrapperManager->isValidUri($value)) {
      return;
    }
    $this->context
      ->buildViolation($constraint->message)
      ->setParameter('%value', $value)
      ->addViolation();
  }

}
