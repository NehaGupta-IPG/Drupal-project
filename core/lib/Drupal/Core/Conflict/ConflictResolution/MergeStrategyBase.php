<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictResolution;

use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines a base class for merge-based conflict resolution strategies.
 */
abstract class MergeStrategyBase implements EventSubscriberInterface {

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
   * Resolves conflicts on entities.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictResolutionEvent $event
   *   The entity conflict resolution event.
   */
  abstract protected function doResolveConflicts(EntityConflictResolutionEvent $event): void;

  /**
   * {@inheritdoc}
   */
  public function resolveConflicts(EntityConflictResolutionEvent $event): void {
    if ($this->isEnabled($event)) {
      $this->doResolveConflicts($event);
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
  public static function getSubscribedEvents(): array {
    return [EntityConflictResolutionEvent::class => [['resolveConflicts']]];
  }

}
