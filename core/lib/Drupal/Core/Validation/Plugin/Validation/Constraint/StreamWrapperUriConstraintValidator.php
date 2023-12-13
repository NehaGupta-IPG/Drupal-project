<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

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
      throw new UnexpectedValueException($value, 'string');
    }
    if (!$constraint instanceof StreamWrapperUriConstraint) {
      throw new UnexpectedTypeException($constraint, StreamWrapperUriConstraint::class);
    }
    if (!$this->streamWrapperManager->isValidUri($value)) {
      $this->context
        ->buildViolation($constraint->message)
        ->setParameter('%value', $value)
        ->addViolation();
      // Do not continue with an invalid URI.
      return;
    }
    $read_write_stream_wrappers = $this->streamWrapperManager->getNames($constraint->filter);
    $uri_scheme = $this->streamWrapperManager->getScheme($value);
    if (in_array($uri_scheme, array_keys($read_write_stream_wrappers))) {
      return;
    }
    $this->context
      ->buildViolation($constraint->invalidSchemeMessage)
      ->setParameter('%scheme', $uri_scheme)
      ->addViolation();
  }

}
