<?php

namespace Drupal\options\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines an interface for predefined options plugins.
 */
interface PredefinedOptionsPluginInterface extends PluginInspectionInterface {

  /**
   * Returns the of allowed values.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *   The field storage definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   (optional) The entity context if known, or NULL if the allowed values
   *   are being collected without the context of a specific entity.
   * @param bool &$cacheable
   *   (optional) If an $entity is provided, the $cacheable parameter should be
   *   modified by reference and set to FALSE if the set of allowed values
   *   returned was specifically adjusted for that entity and cannot not be
   *   reused for other entities. Defaults to TRUE.
   *
   * @return array
   *   The array of allowed values. Keys of the array are the raw stored values
   *   (number or text), values of the array are the display labels. If $entity
   *   is NULL, you should return the list of all the possible allowed values
   *   in any context so that other code (e.g. Views filters) can support the
   *   allowed values for all possible entities and bundles.
   */
  public function getAllowedValues(FieldStorageDefinitionInterface $definition, ?FieldableEntityInterface $entity = NULL, &$cacheable = TRUE);

}
