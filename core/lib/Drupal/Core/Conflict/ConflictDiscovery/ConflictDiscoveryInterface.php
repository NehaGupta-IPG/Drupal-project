<?php

namespace Drupal\Core\Conflict\ConflictDiscovery;

use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface ConflictDiscoveryInterface extends EventSubscriberInterface {

  /**
   * Discovers conflicts on content entities.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent
   *   The entity conflict discovery event.
   */
  public function discoverConflictsContentEntity(EntityConflictDiscoveryEvent $event);

}
