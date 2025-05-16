<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element for the title of an HTML page.
 *
 * This represents the title of the HTML page's body.
 */
#[RenderElement('page_title')]
class PageTitle extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'page_title',
      // The page title: either a string for plain titles or a render array for
      // formatted titles.
      '#title' => NULL,
    ];
  }

  public static function createInstance($theme, $title, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('page_title');
    $instance->theme = $theme;
    $instance->title = $title;
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
      '#title' => $this->title,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
