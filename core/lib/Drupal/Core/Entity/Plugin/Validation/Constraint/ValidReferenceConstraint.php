<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

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
   * Error code when NULL is given for referencing an existing entity.
   */
  public const EMPTY_REFERENCE_ERROR = '38749f33-9a09-4a19-84d0-0edcf54dab82';

  /**
   * Error code when a given new entity cannot be referenced.
   */
  public const NON_REFERENCEABLE_NEW_ENTITY_ERROR = 'c11c9966-888f-4b85-bcf1-c2703fee852a';

  /**
   * Error code when referencing new entities is not allowed.
   */
  public const NEW_ENTITIES_DISALLOWED_ERROR = '1324b010-dcca-467f-a5bb-7a31b54a2eb3';

  /**
   * Error code when a given existing entity cannot be referenced.
   */
  public const NON_REFERENCEABLE_EXISTING_ENTITY_ERROR = 'f4ebb66a-201d-473d-a1a1-b5ea2c9583ed';

  /**
   * Error code when a given existing entity is not found.
   */
  public const NON_EXISTING_ENTITY_ERROR = '11c7bf98-036c-44cb-8224-c1cf072013ff';

  /**
   * {@inheritdoc}
   */
  protected const ERROR_NAMES = [
    self::EMPTY_REFERENCE_ERROR => 'EMPTY_REFERENCE_ERROR',
    self::NON_REFERENCEABLE_NEW_ENTITY_ERROR => 'NON_REFERENCEABLE_NEW_ENTITY_ERROR',
    self::NEW_ENTITIES_DISALLOWED_ERROR => 'NEW_ENTITIES_DISALLOWED_ERROR',
    self::NON_REFERENCEABLE_EXISTING_ENTITY_ERROR => 'NON_REFERENCEABLE_EXISTING_ENTITY_ERROR',
    self::NON_EXISTING_ENTITY_ERROR => 'NON_EXISTING_ENTITY_ERROR',
  ];

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'This entity (%type: %id) cannot be referenced.';

  /**
   * Violation message when the entity does not exist.
   *
   * @var string
   */
  public $nonExistingMessage = 'The referenced entity (%type: %id) does not exist.';

  /**
   * Violation message when a new entity ("autocreate") is invalid.
   *
   * @var string
   */
  public $invalidAutocreateMessage = 'This entity (%type: %label) cannot be referenced.';

  /**
   * Violation message when the target_id is empty.
   *
   * @var string
   */
  public $nullMessage = 'This value should not be null.';

}
