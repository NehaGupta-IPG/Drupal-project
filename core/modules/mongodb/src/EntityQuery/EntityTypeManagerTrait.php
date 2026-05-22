<?php

namespace Drupal\mongodb\EntityQuery;

/**
 * Provides a trait for getting entity type manager service.
 */
trait EntityTypeManagerTrait {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Returns the entity manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManager
   *   The entity type manager service.
   */
  protected function getEntityTypeManager() {
    if (empty($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }

    return $this->entityTypeManager;
  }

}
