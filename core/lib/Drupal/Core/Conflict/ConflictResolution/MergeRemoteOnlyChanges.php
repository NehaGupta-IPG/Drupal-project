<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictResolution;

use Drupal\Core\Conflict\ConflictType;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a merge strategy that privileges remote-only changes.
 */
class MergeRemoteOnlyChanges extends MergeStrategyBase {

  /**
   * {@inheritdoc}
   */
  protected function getMergeStrategyId() : string {
    return 'conflict.merge_remote_only_changes';
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveContentEntityConflicts(EntityConflictResolutionEvent $event): void {
    $remote_entity = $event->getRemoteEntity();
    if (!$remote_entity instanceof ContentEntityInterface) {
      return;
    }
    $result_entity = $event->getResultEntity();
    // This is enforced by the event constructor.
    \assert($result_entity instanceof ContentEntityInterface);

    foreach ($event->getConflicts() as $property => $conflict_type) {
      if ($conflict_type === ConflictType::Remote) {
        $result_entity->set($property, $remote_entity->get($property)->getValue());
        $event->removeConflict($property);
      }
    }
  }

}
