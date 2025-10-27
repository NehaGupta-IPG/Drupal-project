<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictResolution;

use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a base class for conflict merge strategies.
 */
abstract class MergeStrategyBase implements MergeStrategyInterface {

  /**
   * Returns the merge strategy ID.
   *
   * To prevent conflicts the merge strategy ID should be prefixed by the
   * provider's name.
   *
   * @return string
   *   The merge strategy ID.
   */
  abstract protected function getMergeStrategyId() : string;

  /**
   * Resolves conflicts on content entities.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictResolutionEvent $event
   *   The entity conflict discovery event.
   */
  abstract protected function resolveContentEntityConflicts(EntityConflictResolutionEvent $event): void;

  /**
   * {@inheritdoc}
   */
  public function resolveConflicts(EntityConflictResolutionEvent $event): void {
    if ($this->isEnabled($event)) {
      $local_entity = $event->getLocalEntity();
      if ($local_entity instanceof ContentEntityInterface) {
        $this->resolveContentEntityConflicts($event);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function isEnabled(EntityConflictResolutionEvent $event): bool {
    $disabled_merge_strategies = $event->getContextParameter('merge_strategy.disabled', []);
    return !in_array($this->getMergeStrategyId(), $disabled_merge_strategies, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [EntityConflictResolutionEvent::class => [['resolveConflicts']]];
  }

}
