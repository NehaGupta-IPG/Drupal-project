<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element for an entire HTML page: <html> plus its children.
 */
#[RenderElement('html')]
class Html extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'html',
    ];
  }

  public static function createInstance($theme, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('html');
    $instance->theme = $theme;
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
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
