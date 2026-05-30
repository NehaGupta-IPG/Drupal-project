<?php

namespace Drupal\Core\Entity;

/**
 * Provides an interface for entities that have a weight.
 *
 * @ingroup entity_type_characteristics
 *
 * @see \Drupal\Core\Entity\EntityWeightTrait
 */
interface EntityWeightInterface extends EntityInterface {

  /**
   * Gets the entity weight.
   *
   * @return int
   *   The entity weight.
   */
  public function getWeight(): int;

  /**
   * Sets the entity weight.
   *
   * @param int $weight
   *   The entity weight.
   *
   * @return static
   *   The called entity instance.
   */
  public function setWeight(int $weight): static;

}
