<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Check that textarea field contains unique multiple values.
 */
#[Constraint(
  id: 'MultipleUniqueValue',
  label: new TranslatableMarkup('Unique multiple field', [], ['context' => 'Validation'])
)]
class MultipleUniqueValueConstraint extends SymfonyConstraint {

  /**
   * Validation message.
   *
   * @var string
   */
  public string $message;

  /**
   * {@inheritdoc}
   */
  public function __construct(mixed $options = NULL, ?array $groups = NULL, mixed $payload = NULL, ?string $message = 'Add an unique value per line.') {
    $this->message = $message;
    parent::__construct($options, $groups, $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return MultipleUniqueValueConstraintValidator::class;
  }

}
