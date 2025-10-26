<?php

namespace Drupal\Core\Conflict\ConflictResolution;

use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface MergeStrategyInterface extends EventSubscriberInterface {

  /**
   * Returns the merge strategy ID.
   *
   * To prevent conflicts the merge strategy ID should be prefixed by the
   * provider's name.
   *
   * @return string
   *   The merge strategy ID.
   */
  public function getMergeStrategyId() : string;

  /**
   * Resolves conflicts on content entities.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictResolutionEvent
   *   The entity conflict discovery event.
   */
  public function resolveConflictsContentEntity(EntityConflictResolutionEvent $event);

  /**
   * Checks whether this merge strategy is active for the conflict resolution.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictResolutionEvent $event
   *   The conflict resolution event.
   *
   * @return bool
   *   TRUE, if the merge strategy is enabled. FALSE, if it is disabled.
   */
  public function isEnabled(EntityConflictResolutionEvent $event) : bool;

}

