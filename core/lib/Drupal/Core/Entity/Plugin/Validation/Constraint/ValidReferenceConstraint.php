<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 */
#[Constraint(
  id: 'ValidReference',
  label: new TranslatableMarkup('Entity Reference valid reference', [], ['context' => 'Validation'])
)]
class ValidReferenceConstraint extends SymfonyConstraint {

  /**
   * Constructs a valid reference constraint.
   *
   * @param string $message
   *   The message for a non-referenceable existing target.
   * @param string $nonExistingMessage
   *   The message for a nonexistent target.
   * @param string $invalidAutocreateMessage
   *   The message for a non-referenceable new target.
   * @param string $nullMessage
   *   The message for an invalid NULL target ID.
   * @param array|null $groups
   *   The validation groups, or NULL to use the default group.
   * @param mixed $payload
   *   Domain-specific data attached to the constraint.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface|null $handler
   *   The configured selection handler, or NULL to resolve one from the field.
   * @param bool $allowNewEntities
   *   Whether new targets are allowed, subject to validation by the handler.
   */
  public function __construct(
    public $message = 'This entity (%type: %id) cannot be referenced.',
    public $nonExistingMessage = 'The referenced entity (%type: %id) does not exist.',
    public $invalidAutocreateMessage = 'This entity (%type: %label) cannot be referenced.',
    public $nullMessage = 'This value should not be null.',
    ?array $groups = NULL,
    mixed $payload = NULL,
    public ?SelectionInterface $handler = NULL,
    public bool $allowNewEntities = TRUE,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
