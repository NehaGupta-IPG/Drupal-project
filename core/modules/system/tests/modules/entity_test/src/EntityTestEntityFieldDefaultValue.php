<?php

namespace Drupal\entity_test;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Provides entity field default value callbacks.
 */
class EntityTestEntityFieldDefaultValue {

  /**
   * Field default value callback.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being created.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field definition.
   *
   * @return array
   *   An array of default values, in the same format as the $default_value
   *   property.
   *
   * @see \Drupal\field\Entity\FieldConfig::$default_value
   */
  public function defaultValue(FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    // Just wrap around the procedural default value callback.
    return entity_test_field_default_value($entity, $definition);
  }

}
