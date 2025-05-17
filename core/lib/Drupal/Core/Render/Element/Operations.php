<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element for a set of operations links.
 *
 * This is a special case of \Drupal\Core\Render\Element\Dropbutton; the only
 * difference is that it offers themes the possibility to render it differently
 * through a theme suggestion.
 *
 * @see \Drupal|Core\Render\Element\DropButton
 */
#[RenderElement('operations')]
class Operations extends Dropbutton {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'links__dropbutton__operations',
    ] + parent::getInfo();
  }

  /**
   * Create an operations element.
   *
   * @param mixed $theme
   *   Automatically converted param.
   * @param mixed $pre_render
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
  public static function createInstance($theme, $pre_render, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('operations');
    $instance->theme = $theme;
    $instance->pre_render = $pre_render;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#theme' => $this->theme,
      '#pre_render' => $this->pre_render,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
