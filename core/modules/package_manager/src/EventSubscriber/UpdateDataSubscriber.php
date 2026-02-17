<?php

declare(strict_types=1);

namespace Drupal\package_manager\EventSubscriber;

use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\update\UpdateManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears stale update data once staged changes have been applied.
 *
 * @internal
 *   This is an internal part of Package Manager and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class UpdateDataSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly UpdateManagerInterface $updateManager) {
  }

  /**
   * Clears stale update data.
   *
   * This will always run after any stage directory changes are applied to the
   * active directory, since it's likely that core and/or multiple extensions
   * have been added, removed, or updated.
   */
  public function clearData(): void {
    // Clear anything we have now.
    update_storage_clear();
    // Create new fetch tasks.
    $this->updateManager->refreshUpdateData();
    // Actually fetch the new data.
    update_fetch_data();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostApplyEvent::class => ['clearData', 1000],
    ];
  }

}
