<?php

declare(strict_types=1);

namespace Drupal\user\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows modules to prepare an entity translation for forced unpublishing.
 *
 * @internal
 */
final class EntityCancellationUnpublishEvent extends Event {

  public function __construct(
    public readonly ContentEntityInterface $entity,
  ) {
  }

}
