<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictDiscovery;

use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines a default class for conflict discovery.
 */
abstract class ConflictDiscoveryBase implements EventSubscriberInterface {

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
