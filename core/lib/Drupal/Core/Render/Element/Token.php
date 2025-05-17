<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Stores token data in a hidden form field.
 *
 * This is generally used to protect against cross-site forgeries. A token
 * element is automatically added to each Drupal form by an implementation of
 * \Drupal\Core\Form\FormBuilderInterface::prepareForm() so you don't generally
 * have to add one yourself.
 */
#[FormElement('token')]
class Token extends Hidden {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#pre_render' => [
        [static::class, 'preRenderHidden'],
      ],
      '#theme' => 'input__hidden',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // This should be a string, but allow other scalars since they might be
      // valid input in programmatic form submissions.
      return is_scalar($input) ? (string) $input : '';
    }
    return NULL;
  }

  public static function createInstance($input, $pre_render, $theme, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('token');
    $instance->input = $input;
    $instance->pre_render = $pre_render;
    $instance->theme = $theme;
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
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
