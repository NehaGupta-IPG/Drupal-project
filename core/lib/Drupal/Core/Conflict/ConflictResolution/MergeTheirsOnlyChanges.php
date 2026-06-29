<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictResolution;

use Drupal\Core\Conflict\ConflictType;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Merge-based conflict resolution strategy that privileges theirs-only changes.
 */
class MergeTheirsOnlyChanges extends MergeStrategyBase {

  /**
   * {@inheritdoc}
   */
  protected function getMergeStrategyId() : string {
    return 'conflict.merge_theirs_only_changes';
  }

  /**
   * {@inheritdoc}
   */
  protected function doResolveConflicts(EntityConflictResolutionEvent $event): void {
    $theirs_entity = $event->getTheirsEntity();
    if (!$theirs_entity instanceof ContentEntityInterface) {
      return;
    }
    $result_entity = $event->getResultEntity();
    // This is enforced by the event constructor.
    \assert($result_entity instanceof ContentEntityInterface);

    foreach ($event->getConflicts() as $property => $conflict_type) {
      if ($conflict_type === ConflictType::TheirsOnly) {
        $result_entity->set($property, $theirs_entity->get($property)->getValue());
        $event->resolveConflict($property);
      }
    }
  }

}
