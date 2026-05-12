<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictResolver;

use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Manages conflict resolving.
 */
class ConflictResolverManager implements ConflictResolverManagerInterface {

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflicts(
    EntityInterface $ours,
    EntityInterface $theirs,
    EntityInterface $base,
    ?EntityInterface $result = NULL,
    ParameterBag $context = new ParameterBag(),
    ?array $conflicts = NULL,
  ) : array {
    // If there are no passed conflicts given, attempt to discover them.
    $conflicts ??= $this->getConflicts($ours, $theirs, $base, $context);

    if (\count($conflicts) > 0) {
      $result ??= $ours;
      $event = new EntityConflictResolutionEvent($ours, $theirs, $base, $result, $conflicts, $context);
      // Fire an event to allow listeners to automatically resolve conflicts.
      $this->eventDispatcher->dispatch($event);
      $conflicts = $event->getConflicts();
    }

    return $conflicts;
  }

  /**
   * {@inheritdoc}
   */
  public function getConflicts(
    EntityInterface $ours,
    EntityInterface $theirs,
    EntityInterface $base,
    ParameterBag $context = new ParameterBag(),
  ) : array {
    $event = new EntityConflictDiscoveryEvent($ours, $theirs, $base, $context);
    // Fire an event to allow listeners to build a list of conflicting
    // properties.
    $this->eventDispatcher->dispatch($event);
    return $event->getConflicts();
  }

}
