<?php

namespace Drupal\Core\Field;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines a base class for all field storage definition events.
 */
class FieldStorageDefinitionEvent extends Event {

  /**
   * The field storage definition.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface
   */
  protected $fieldStorageDefinition;

  /**
   * The original field storage definition.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface
   */
  protected $original;

  public function __construct(FieldStorageDefinitionInterface $field_storage_definition, ?FieldStorageDefinitionInterface $original = NULL) {
    $this->fieldStorageDefinition = $field_storage_definition;
    $this->original = $original;
  }

  /**
   * The field storage definition.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
   *   The field storage definition for the entity.
   */
  public function getFieldStorageDefinition() {
    return $this->fieldStorageDefinition;
  }

  /**
   * The original field storage definition.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
   *   The field storage definition for the original entity.
   */
  public function getOriginal() {
    return $this->original;
  }

}
