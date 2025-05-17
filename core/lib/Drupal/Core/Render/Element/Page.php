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

  public static function createInstance($theme, $title, $type, $theme_wrappers, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
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
  public function toRenderArray() {
    return [
      '#theme' => $this->theme,
      '#title' => $this->title,
      '#type' => $this->type,
      '#theme_wrappers' => $this->theme_wrappers,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
