<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

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

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#theme' => $this->theme,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
