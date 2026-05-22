<?php

namespace Drupal\mongodb\EntityQuery;

/**
 * Provides a trait for getting entity field manager service.
 */
trait EntityFieldManagerTrait {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Returns the entity manager service.
   *
   * @return \Drupal\Core\Entity\EntityFieldManager
   *   The entity field manager service.
   */
  protected function getEntityFieldManager() {
    if (empty($this->entityFieldManager)) {
      $this->entityFieldManager = \Drupal::service('entity_field.manager');
    }

    return $this->entityFieldManager;
  }

}
