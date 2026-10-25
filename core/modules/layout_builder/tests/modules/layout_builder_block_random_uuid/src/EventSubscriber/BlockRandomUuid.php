<?php

namespace Drupal\layout_builder_block_random_uuid\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber for testing section storage alteration.
 *
 * @see \Drupal\layout_builder\Event\PrepareLayoutEvent
 * @see \Drupal\layout_builder\Element\LayoutBuilder::prepareLayout()
 */
class BlockRandomUuid implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = [
      'onBuildRender',
      50,
    ];
    return $events;
  }

  /**
   * Builds render arrays for block plugins and sets it on the event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();
    if (!$block instanceof BlockPluginInterface) {
      return;
    }

    $build = $event->getBuild();

    $build['#attributes']['data-layout-builder-block-random-uuid'] = \Drupal::service('uuid')->generate();

    $event->setBuild($build);
  }

}
