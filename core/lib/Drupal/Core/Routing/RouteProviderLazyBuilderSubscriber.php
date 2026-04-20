<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tracks the rebuild state of the RouteProviderLazyBuilder.
 *
 * The state is held on this separate subscriber service (rather than on the
 * RouteProviderLazyBuilder itself) so that replacing 'router.route_provider',
 * as kernel tests may do, does not detach the routing event listeners.
 */
class RouteProviderLazyBuilderSubscriber implements EventSubscriberInterface {

  /**
   * Whether the router has been rebuilt since the last markStale() call.
   */
  protected bool $rebuilt = TRUE;

  /**
   * Whether a rebuild is currently in progress.
   *
   * Used to prevent recursive router rebuilds during module installation.
   * Recursive rebuilds can occur when route information is required by alter
   * hooks that are triggered during a rebuild, for example,
   * hook_menu_links_discovered_alter().
   */
  protected bool $rebuilding = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RoutingEvents::DYNAMIC => [['onRouterRebuilding', 3000]],
      RoutingEvents::FINISHED => [['onRouterRebuildFinished', -3000]],
    ];
  }

  /**
   * Marks the routing table as stale so the next lookup triggers a rebuild.
   */
  public function markStale(): void {
    $this->rebuilt = FALSE;
  }

  /**
   * Returns TRUE if the table has been rebuilt since the last markStale().
   */
  public function hasRebuilt(): bool {
    return $this->rebuilt;
  }

  /**
   * Returns TRUE if a rebuild is currently in progress.
   */
  public function isRebuilding(): bool {
    return $this->rebuilding;
  }

  /**
   * Records that a router rebuild has started.
   */
  public function onRouterRebuilding(): void {
    $this->rebuilding = TRUE;
  }

  /**
   * Records that a router rebuild has finished.
   */
  public function onRouterRebuildFinished(): void {
    $this->rebuilding = FALSE;
    $this->rebuilt = TRUE;
  }

}
