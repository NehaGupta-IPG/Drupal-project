<?php

namespace Drupal\Core\Entity;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines a base class for all entity type events.
 */
class EntityTypeEvent extends Event {

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The original entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $original;

  public function __construct(EntityTypeInterface $entity_type, ?EntityTypeInterface $original = NULL) {
    $this->entityType = $entity_type;
    $this->original = $original;
  }

  /**
   * The entity type the event refers to.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type.
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * The original entity type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The original entity type.
   */
  public function getOriginal() {
    return $this->original;
  }

}
