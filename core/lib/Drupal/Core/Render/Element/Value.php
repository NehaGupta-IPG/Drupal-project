<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for storage of internal information.
 *
 * Unlike \Drupal\Core\Render\Element\Hidden, this information is not sent to
 * the browser in a hidden form field, but only stored in the form array for use
 * in validation and submit processing.
 *
 * Properties:
 * - #value: The value of the form element that cannot be edited by the user.
 *
 * Usage Example:
 * @code
 * $form['entity_id'] = ['#type' => 'value', '#value' => $entity_id];
 * @endcode
 */
#[FormElement('value')]
class Value extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
    ];
  }

  public static function createInstance($input, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('value');
    $instance->input = $input;
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
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
