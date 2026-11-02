<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Email;

/**
 * Validates email addresses using Drupal mailbox validation.
 */
#[Constraint(
  id: 'Email',
  label: new TranslatableMarkup('Email', [], ['context' => 'Validation'])
)]
class EmailConstraint extends Email {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ?string $message = NULL,
    ?string $mode = NULL,
    ?callable $normalizer = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(NULL, $message, $mode, $normalizer, $groups, $payload);
    $this->mode = static::VALIDATION_MODE_STRICT;
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return EmailConstraintValidator::class;
  }

}
