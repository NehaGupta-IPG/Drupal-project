<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictDiscovery;

use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines an interface for event subscribers that can discover conflicts.
 */
interface ConflictDiscoveryInterface extends EventSubscriberInterface {

  /**
   * Discovers conflicts on entities.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent $event
   *   The entity conflict discovery event.
   */
  public function discoverConflicts(EntityConflictDiscoveryEvent $event): void;

}
