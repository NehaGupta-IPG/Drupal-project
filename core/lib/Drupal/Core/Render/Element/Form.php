<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\ElementInfoManager;

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

  public static function createInstance($method, $theme_wrappers, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('form');
    $instance->method = $method;
    $instance->theme_wrappers = $theme_wrappers;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#method' => $this->method,
      '#theme_wrappers' => $this->theme_wrappers,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
