<?php

namespace Drupal\Core\Entity;

/**
 * Provides an interface for access to an entity's published state.
 *
 * @ingroup entity_type_characteristics
 */
interface EntityPublishedInterface extends EntityInterface {

  /**
   * Denotes that the entity is not published.
   */
  const NOT_PUBLISHED = 0;

  /**
   * Denotes that the entity is published.
   */
  const PUBLISHED = 1;

  /**
   * Returns whether or not the entity is published.
   *
   * @return bool
   *   TRUE if the entity is published, FALSE otherwise.
   */
  public function isPublished();

  /**
   * Sets the entity as published.
   *
   * @return $this
   *
   * @see \Drupal\Core\Entity\EntityPublishedInterface::setUnpublished()
   */
  public function setPublished();

  /**
   * Sets the entity as unpublished.
   *
   * @return $this
   */
  public function setUnpublished();

}
