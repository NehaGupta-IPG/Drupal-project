<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for a form.
 */
#[RenderElement('form')]
class Form extends RenderElementBase {


  /**
   * @var mixed Automatically converted property
   */
  public $method;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#method' => 'post',
      '#theme_wrappers' => ['form'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#method' => $this->method,
      '#theme_wrappers' => $this->theme_wrappers,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
