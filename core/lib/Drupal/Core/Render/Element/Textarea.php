<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for input of multiple-line text.
 *
 * Properties:
 * - #rows: Number of rows in the text box.
 * - #cols: Number of columns in the text box.
 * - #resizable: Controls whether the text area is resizable.  Allowed values
 *   are "none", "vertical", "horizontal", or "both" (defaults to "vertical").
 * - #maxlength: The maximum amount of characters to accept as input.
 *
 * Usage example:
 * @code
 * $form['text'] = [
 *   '#type' => 'textarea',
 *   '#title' => $this->t('Text'),
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textfield
 * @see \Drupal\filter\Element\TextFormat
 */
#[FormElement('textarea')]
class Textarea extends FormElementBase {


  /**
   * @var mixed Automatically converted property
   */
  public $cols;

  /**
   * @var mixed Automatically converted property
   */
  public $rows;

  /**
   * @var mixed Automatically converted property
   */
  public $resizable;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#cols' => 60,
      '#rows' => 5,
      '#resizable' => 'vertical',
      '#process' => [
        [static::class, 'processAjaxForm'],
        [static::class, 'processGroup'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderGroup'],
        [static::class, 'preRenderAttachments'],
      ],
      '#theme' => 'textarea',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Adds the textarea resize library.
   */
  public static function preRenderAttachments($element): array {
    $element['#attached']['library'][] = 'core/drupal.textarea-resize';
    return $element;
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

  public static function createInstance($input, $cols, $rows, $resizable, $process, $pre_render, $theme, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('textarea');
    $instance->input = $input;
    $instance->cols = $cols;
    $instance->rows = $rows;
    $instance->resizable = $resizable;
    $instance->process = $process;
    $instance->pre_render = $pre_render;
    $instance->theme = $theme;
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
      '#cols' => $this->cols,
      '#rows' => $this->rows,
      '#resizable' => $this->resizable,
      '#process' => $this->process,
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#theme_wrappers' => $this->theme_wrappers,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
