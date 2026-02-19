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
   * @var EntityTypeInterface
   */
  protected $entityType;

  /**
   * The original entity type.
   *
   * @var EntityTypeInterface
   */
  protected $original;

  /**
   * Constructs a new EntityTypeEvent.
   *
   * @param EntityTypeInterface $entity_type
   *   The field storage definition.
   * @param EntityTypeInterface $original
   *   (optional) The original entity type. This should be passed only when
   *   updating the entity type.
   */
  public function __construct(EntityTypeInterface $entity_type, ?EntityTypeInterface $original = NULL) {
    $this->entityType = $entity_type;
    $this->original = $original;
  }

  /**
   * The entity type the event refers to.
   *
   * @return EntityTypeInterface
   *   The entity type.
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * The original entity type.
   *
   * @return EntityTypeInterface
   *   The original entity type.
   */
  public function getOriginal() {
    return $this->original;
  }

}
