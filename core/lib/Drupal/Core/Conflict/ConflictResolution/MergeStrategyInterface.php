<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictResolution;

use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines an interface for event subscribers that can resolve conflicts.
 */
interface MergeStrategyInterface extends EventSubscriberInterface {

  /**
   * Resolves conflicts on an entity.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictResolutionEvent $event
   *   The entity conflict resolution event.
   */
  public function resolveConflicts(EntityConflictResolutionEvent $event): void;

}
