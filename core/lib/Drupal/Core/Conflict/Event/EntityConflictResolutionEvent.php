<?php

namespace Drupal\Core\Conflict\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Defines an event to resolve entity conflicts.
 */
class EntityConflictResolutionEvent extends EntityConflictDiscoveryEvent {

  public function __construct(
    EntityInterface $localEntity,
    EntityInterface $remoteEntity,
    EntityInterface $baseEntity,
    protected EntityInterface $resultEntity,
    array $conflicts,
    ParameterBag $context = new ParameterBag(),
  ) {
    parent::__construct($localEntity, $remoteEntity, $baseEntity, $context);
    $this->conflicts = $conflicts;
  }

  /**
   * Creates a conflict resolution event object from a conflict discovery event.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent $event
   *   The conflict discovery event.
   * @param \Drupal\Core\Entity\EntityInterface $result
   *   The result entity, on which to apply the result.
   *
   * @return static
   */
  public static function createFromConflictDiscoveryEvent(EntityConflictDiscoveryEvent $event, EntityInterface $result): static {
    return new static($event->localEntity, $event->remoteEntity, $event->baseEntity, $result, $event->conflicts, $event->context);
  }

  /**
   * Returns the result entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The result entity.
   */
  public function getResultEntity() : EntityInterface {
    return $this->resultEntity;
  }

  /**
   * Removes a conflict.
   *
   * This method should be called when a conflict has been resolved or the
   * property value on the resulting entity satisfies the merge strategy.
   *
   * @param string $property
   *   The conflicting property.
   */
  public function removeConflict(string $property): static {
    unset($this->conflicts[$property]);
    return $this;
  }

}
