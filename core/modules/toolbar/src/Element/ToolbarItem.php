<?php

namespace Drupal\toolbar\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Url;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a toolbar item that is wrapped in markup for common styling.
 *
 * The 'tray' property contains a renderable array.
 */
#[RenderElement('toolbar_item')]
class ToolbarItem extends RenderElementBase {


  /**
   * @var mixed Automatically converted property
   */
  public $ab;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderToolbarItem'],
      ],
      'tab' => [
        '#type' => 'link',
        '#title' => '',
        '#url' => Url::fromRoute('<front>'),
      ],
    ];
  }

  /**
   * Provides markup for associating a tray trigger with a tray element.
   *
   * A tray is a responsive container that wraps renderable content. Trays
   * present content well on small and large screens alike.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   A renderable array.
   */
  public static function preRenderToolbarItem($element) {
    $id = $element['#id'];

    // Provide attributes for a toolbar item.
    $attributes = [
      'id' => $id,
    ];

    // If tray content is present, markup the tray and its associated trigger.
    if (!empty($element['tray'])) {
      // Provide attributes necessary for trays.
      $attributes += [
        'data-toolbar-tray' => $id . '-tray',
        'role' => 'button',
        'aria-pressed' => 'false',
      ];

      // Merge in module-provided attributes.
      $element['tab'] += ['#attributes' => []];
      $element['tab']['#attributes'] += $attributes;
      $element['tab']['#attributes']['class'][] = 'trigger';

      // Provide attributes for the tray theme wrapper.
      $attributes = [
        'id' => $id . '-tray',
        'data-toolbar-tray' => $id . '-tray',
      ];
      // Merge in module-provided attributes.
      if (!isset($element['tray']['#wrapper_attributes'])) {
        $element['tray']['#wrapper_attributes'] = [];
      }
      $element['tray']['#wrapper_attributes'] += $attributes;
      $element['tray']['#wrapper_attributes']['class'][] = 'toolbar-tray';
    }

    $element['tab']['#attributes']['class'][] = 'toolbar-item';

    return $element;
  }

  /**
   * Create a toolbar_item element.
   *
   * @param mixed $pre_render
   *   Automatically converted param.
   * @param mixed $ab
   *   Automatically converted param.
   * @param mixed $type
   *   Automatically converted param.
   * @param mixed $defaults_loaded
   *   Automatically converted param.
   * @param ?\Drupal\Core\Render\ElementInfoManager $elementInfoManager
   *   The element info manager.
   *
   * @return static
   */
  public static function createInstance($pre_render, $ab, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('toolbar_item');
    $instance->pre_render = $pre_render;
    $instance->ab = $ab;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#pre_render' => $this->pre_render,
      '#ab' => $this->ab,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
