<?php

namespace Drupal\Core\Conflict\ConflictResolution;

use Drupal\Core\Conflict\ConflictTypes;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;

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
  public function resolveContentEntityConflicts(EntityConflictResolutionEvent $event): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $remote_entity */
    $remote_entity = $event->getRemoteEntity();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $result_entity */
    $result_entity = $event->getResultEntity();

    foreach ($event->getConflicts() as $property => $conflict_type) {
      if ($conflict_type === ConflictTypes::ConflictTypeRemote) {
        $result_entity->set($property, $remote_entity->get($property)->getValue());
        $event->removeConflict($property);
      }
    }
  }

}
