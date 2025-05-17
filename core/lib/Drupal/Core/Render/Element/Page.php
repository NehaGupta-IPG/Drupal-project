<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element for the content of an HTML page.
 *
 * This represents the "main part" of the HTML page's body; see html.html.twig.
  */
#[RenderElement('page')]
class Page extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'page',
      '#title' => '',
    ];
  }

  /**
   * Create a page element.
   *
   * @param mixed $theme
   *   Automatically converted param.
   * @param mixed $title
   *   Automatically converted param.
   * @param mixed $type
   *   Automatically converted param.
   * @param mixed $theme_wrappers
   *   Automatically converted param.
   * @param mixed $defaults_loaded
   *   Automatically converted param.
   * @param ?\Drupal\Core\Render\ElementInfoManager $elementInfoManager
   *   The element info manager.
   *
   * @return static
   */
  public static function createInstance($theme, $title, $type, $theme_wrappers, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('page');
    $instance->theme = $theme;
    $instance->title = $title;
    $instance->type = $type;
    $instance->theme_wrappers = $theme_wrappers;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#theme' => $this->theme,
      '#title' => $this->title,
      '#type' => $this->type,
      '#theme_wrappers' => $this->theme_wrappers,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
