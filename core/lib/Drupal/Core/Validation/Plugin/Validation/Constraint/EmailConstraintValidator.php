<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Component\Utility\EmailValidator;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates email fields using the same mailbox rules as email form elements.
 */
class EmailConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(protected EmailValidatorInterface $emailValidator = new EmailValidator()) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('email.validator'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof Email) {
      throw new UnexpectedTypeException($constraint, Email::class);
    }
    if ($value === NULL || $value === '') {
      return;
    }
    if (!is_scalar($value) && !$value instanceof \Stringable) {
      throw new UnexpectedValueException($value, 'string');
    }
    $value = (string) $value;
    if ($value === '') {
      return;
    }
    if ($constraint->normalizer !== NULL) {
      $value = ($constraint->normalizer)($value);
    }
    if (!$this->emailValidator->isValid($value)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('{{ value }}', $this->formatValue($value))
        ->setCode(Email::INVALID_FORMAT_ERROR)
        ->addViolation();
    }
  }

}
