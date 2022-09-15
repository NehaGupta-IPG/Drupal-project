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
   * Converts an entity to a label.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entity objects.
   * @param array $element
   *   The form element.
   *
   * @return array
   *   An array of entity labels.
   */
  public function getAutocompleteLabels(array $entities, array $element): array;

}
