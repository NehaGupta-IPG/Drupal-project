<?php

namespace Drupal\Core\Entity\EntityReferenceSelection;

/**
 * Interface for Selection plugins that support custom autocomplete text.
 *
 * @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager
 * @see \Drupal\Core\Entity\Annotation\EntityReferenceSelection
 * @see plugin_api
 */
interface SelectionWithAutocompleteLabelsInterface {

  /**
   * Converts an array of entities into IDs and labels to use for autocomplete.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entity objects.
   * @param array $element
   *   The form element for the autocomplete widget.
   *
   * @return array[]
   *   An array of arrays. Each array must contain the following keys:
   *     - id: The machine-friendly unique key that identifies the entity. Can
   *       be NULL if the entity is new.
   *     - label: The human-friendly label for the entity. This value should not
   *       contain any HTML tags and should already be sanitized for display in
   *       page markup. It must not contain raw user input.
   */
  public function getAutocompleteLabels(array $entities, array $element): array;

}
