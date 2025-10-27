<?php

namespace Drupal\Core\Conflict\ConflictDiscovery;

use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a default class for conflict discovery.
 */
abstract class ConflictDiscoveryBase implements ConflictDiscoveryInterface {

  /**
   * Discovers conflicts on content entities.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent $event
   *   The entity conflict discovery event.
   */
  abstract protected function discoverContentEntityConflicts(EntityConflictDiscoveryEvent $event): void;

  /**
   * {@inheritdoc}
   */
  public function discoverConflicts(EntityConflictDiscoveryEvent $event): void {
    $local_entity = $event->getLocalEntity();
    if ($local_entity instanceof ContentEntityInterface) {
      $this->discoverContentEntityConflicts($event);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [EntityConflictDiscoveryEvent::class => [['discoverConflicts']]];
  }

}
