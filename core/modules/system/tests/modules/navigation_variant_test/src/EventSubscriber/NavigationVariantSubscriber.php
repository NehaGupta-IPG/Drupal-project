<?php

declare(strict_types=1);

namespace Drupal\navigation_variant_test\EventSubscriber;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A variant for testing, which displays typical Drupal global blocks.
 *
 * Including:
 *  - Breadcrumb
 *  - Local tasks
 *  - Local actions
 *
 * This variant is useful for testing without Block module.
 */
final class NavigationVariantSubscriber implements EventSubscriberInterface {

  /**
   * Selects the page display variant.
   */
  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event): void {
    // Plugin ID for
    // \Drupal\navigation_variant_test\Plugin\DisplayVariant\NavigationVariant.
    $event->setPluginId('navigation_variant');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[RenderEvents::SELECT_PAGE_DISPLAY_VARIANT][] = ['onSelectPageDisplayVariant'];
    return $events;
  }

}
