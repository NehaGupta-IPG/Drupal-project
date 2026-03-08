<?php

namespace Drupal\layout_builder\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hides components marked as hidden.
 */
class SectionComponentHidden implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run before BlockComponentRenderArray (priority 100), so that we can
    // hide the component (by stopping propagation) or, in the preview, show
    // the user a message about the component being hidden.
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 500];
    return $events;
  }

  /**
   * Stops propagation if the component is hidden, or gives message in preview.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component build render array event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $hidden = $event->getComponent()->get('hidden') ?: FALSE;
    if (!$hidden) {
      return;
    }

    if ($event->inPreview()) {
      $block = $event->getPlugin();
      if (!$block instanceof BlockPluginInterface) {
        return;
      }

      $content = [
        '#markup' => $this->t('The "@block" block is hidden', ['@block' => $block->label()]),
      ];

      $build = [
        '#theme' => 'block',
        '#configuration' => array_merge($block->getConfiguration(), ['label_display' => FALSE]),
        '#plugin_id' => $block->getPluginId(),
        '#base_plugin_id' => $block->getBaseId(),
        '#derivative_plugin_id' => $block->getDerivativeId(),
        '#weight' => $event->getComponent()->getWeight(),
        '#attributes' => [
          'class' => ['layout-builder-block-hidden'],
        ],
        'content' => $content,
      ];

      $event->setBuild($build);
    }

    $event->stopPropagation();
  }

}
