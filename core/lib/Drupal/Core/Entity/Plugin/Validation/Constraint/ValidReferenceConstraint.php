<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 *
 * The message and nonExistingMessage properties describe invalid target IDs;
 * invalidAutocreateMessage describes rejected new entities. The other reference
 * messages provide presentation variants for autocomplete inputs.
 */
#[Constraint(
  id: 'ValidReference',
  label: new TranslatableMarkup('Entity Reference valid reference', [], ['context' => 'Validation'])
)]
class ValidReferenceConstraint extends SymfonyConstraint {

  /**
   * An existing target entity cannot be referenced.
   */
  public const EXISTING_ENTITY_NOT_REFERENCEABLE_ERROR = '34f3db44-5c1f-43d3-902d-e321164abdb1';

  /**
   * A target entity ID does not identify an existing entity.
   */
  public const ENTITY_DOES_NOT_EXIST_ERROR = 'bf8f636a-d2aa-4b3a-9627-f243865a88b5';

  /**
   * An unsaved target entity cannot be referenced.
   */
  public const NEW_ENTITY_NOT_REFERENCEABLE_ERROR = 'ccfdbcf5-5564-4b93-965d-8f58c9c183e8';

  /**
   * {@inheritdoc}
   */
  protected const ERROR_NAMES = [
    self::EXISTING_ENTITY_NOT_REFERENCEABLE_ERROR => 'EXISTING_ENTITY_NOT_REFERENCEABLE_ERROR',
    self::ENTITY_DOES_NOT_EXIST_ERROR => 'ENTITY_DOES_NOT_EXIST_ERROR',
    self::NEW_ENTITY_NOT_REFERENCEABLE_ERROR => 'NEW_ENTITY_NOT_REFERENCEABLE_ERROR',
  ];

  public function __construct(
    public $message = 'This entity (%type: %id) cannot be referenced.',
    public $nonExistingMessage = 'The referenced entity (%type: %id) does not exist.',
    public $invalidAutocreateMessage = 'The selected @entity_type cannot be referenced.',
    public $nullMessage = 'This value should not be null.',
    ?array $groups = NULL,
    mixed $payload = NULL,
    public $noReferenceableMatchMessage = 'No @entity_type matching %value can be referenced.',
    public $noReferenceableSelectionMessage = 'The selected @entity_type cannot be referenced.',
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
