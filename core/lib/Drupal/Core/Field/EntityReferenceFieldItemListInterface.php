<?php

namespace Drupal\Core\Field;

/**
 * Interface for entity reference lists of field items.
 */
interface EntityReferenceFieldItemListInterface extends FieldItemListInterface {

  /**
   * Gets the entities referenced by this field, preserving field item deltas.
   *
   * * @param string $langcode
   *   The language to return the referenced entities in. Defaults to the
   *   default language for each entity if not defined.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects keyed by field item deltas.
   */
  public function referencedEntities(string $langcode);

}
