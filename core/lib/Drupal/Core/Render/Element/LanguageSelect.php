<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for selecting a language.
 *
 * This does not render an actual form element, but always returns the value of
 * the default language. It is then extended by Language module via
 * language_element_info_alter() to provide a proper language selector.
 *
 * @see language_element_info_alter()
 */
#[FormElement('language_select')]
class LanguageSelect extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#default_value' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ];
  }

  /**
   * Create a language_select element.
   *
   * @param mixed $input
   *   Automatically converted param.
   * @param mixed $default_value
   *   Automatically converted param.
   * @param mixed $value_callback
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
  public static function createInstance($input, $default_value, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('language_select');
    $instance->input = $input;
    $instance->default_value = $default_value;
    $instance->value_callback = $value_callback;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#input' => $this->input,
      '#default_value' => $this->default_value,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
