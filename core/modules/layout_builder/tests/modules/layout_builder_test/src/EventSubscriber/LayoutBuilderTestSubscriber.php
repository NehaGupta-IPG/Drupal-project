<?php

declare(strict_types=1);

namespace Drupal\layout_builder_test\EventSubscriber;

use Drupal\layout_builder\Event\SectionBuildRegionsRenderArrayEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test event subscriber for layout builder region render array events.
 */
class LayoutBuilderTestSubscriber implements EventSubscriberInterface {

  /**
   * Whether the subscriber is active.
   */
  private bool $active = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Add two subscribers, one to run before core subscriber and one after.
    // @see \Drupal\layout_builder\EventSubscriber\SectionRegionsRenderArraySubscriber
    return [
      SectionBuildRegionsRenderArrayEvent::class => [
        ['beforeBuildRegionsRenderArray', 1],
        ['afterBuildRegionsRenderArray', -1],
      ],
    ];
  }

  /**
   * Activates or deactivates the subscriber.
   *
   * @param bool $active
   *   Whether to activate the subscriber.
   */
  public function setActive(bool $active): void {
    $this->active = $active;
  }

  /**
   * Reacts before layout_builder is building the regions.
   *
   * @param \Drupal\layout_builder\Event\SectionBuildRegionsRenderArrayEvent $event
   *   The event object.
   */
  public function beforeBuildRegionsRenderArray(SectionBuildRegionsRenderArrayEvent $event): void {
    if ($this->active) {
      $regions = $event->getRegions();
      $regions['main']['before'] = [
        '#markup' => '3rd party: before',
      ];
      $event->setRegions($regions);
    }
  }

  /**
   * Reacts after layout_builder is building the regions.
   *
   * @param \Drupal\layout_builder\Event\SectionBuildRegionsRenderArrayEvent $event
   *   The event object.
   */
  public function afterBuildRegionsRenderArray(SectionBuildRegionsRenderArrayEvent $event): void {
    if ($this->active) {
      $regions = $event->getRegions();
      $regions['main']['after'] = [
        '#markup' => '3rd party: after',
      ];
      // Alter also content provided by the core's subscriber.
      $regions['main']['first-uuid']['content']['#markup'] = '3rd party: replaced';
      $event->setRegions($regions);
    }
  }

}
