<?php

namespace Drupal\Core\Conflict\Event;

/**
 * Defines events for entity conflict discovery and conflict resolution.
 *
 * @see \Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent
 * @see \Drupal\Core\Conflict\Event\EntityConflictResolutionEvent
 */
final class EntityConflictEvents {

  /**
   * The name of the event fired when searching for conflicts.
   *
   * @Event
   */
  const ENTITY_CONFLICT_DISCOVERY = 'entity_conflict.discovery';

  /**
   * The name of the event fired when resolving conflicts.
   *
   * @Event
   */
  const ENTITY_CONFLICT_RESOLVE = 'entity_conflict.resolve';

}
