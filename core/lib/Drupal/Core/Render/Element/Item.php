<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a display-only form element with an optional title and description.
 *
 * Note: since this is a read-only field, setting the #required property will do
 * nothing except theme the form element to look as if it were actually required
 * (i.e. by placing a red star next to the #title).
 */
#[FormElement('item')]
class Item extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      // Forms that show author fields to both anonymous and authenticated users
      // need to dynamically switch between #type 'textfield' and #type 'item'
      // to automatically take over the authenticated user's information.
      // Therefore, we allow #type 'item' to receive input, which is internally
      // assigned by Form API based on the #default_value or #value properties.
      '#input' => TRUE,
      '#markup' => '',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  public static function createInstance($input, $markup, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('item');
    $instance->input = $input;
    $instance->markup = $markup;
    $instance->theme_wrappers = $theme_wrappers;
    $instance->value_callback = $value_callback;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#input' => $this->input,
      '#markup' => $this->markup,
      '#theme_wrappers' => $this->theme_wrappers,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
