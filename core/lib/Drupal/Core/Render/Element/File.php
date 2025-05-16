<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for uploading a file.
 *
 * If you add this element to a form the enctype="multipart/form-data" attribute
 * will automatically be added to the form element.
 *
 * Properties:
 * - #multiple: A Boolean indicating whether multiple files may be uploaded.
 * - #size: The size of the file input element in characters.
 *
 * The value of this form element will always be an array of
 * \Symfony\Component\HttpFoundation\File\UploadedFile objects, regardless of
 * whether #multiple is TRUE or FALSE
 */
#[FormElement('file')]
class File extends FormElementBase {

  
  /**
   * @var mixed Automatically converted property
   */
  public $multiple;
  
  /**
   * @var mixed Automatically converted property
   */
  public $size;
  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#process' => [
        [static::class, 'processFile'],
      ],
      '#size' => 60,
      '#pre_render' => [
        [static::class, 'preRenderFile'],
      ],
      '#theme' => 'input__file',
      '#theme_wrappers' => ['form_element'],
      '#value_callback' => [
        [static::class, 'valueCallback'],
      ],
    ];
  }

  /**
   * Processes a file upload element, make use of #multiple if present.
   */
  public static function processFile(&$element, FormStateInterface $form_state, &$complete_form) {
    if ($element['#multiple']) {
      $element['#attributes']['multiple'] = 'multiple';
      $element['#name'] .= '[]';
    }
    return $element;
  }

  /**
   * Prepares a #type 'file' render element for input.html.twig.
   *
   * For assistance with handling the uploaded file correctly, see the API
   * provided by file.api.php.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #name, #size, #description, #required,
   *   #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderFile($element) {
    $element['#attributes']['type'] = 'file';
    Element::setAttributes($element, ['id', 'name', 'size']);
    static::setAttributes($element, ['js-form-file', 'form-file']);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE) {
      return NULL;
    }
    $parents = $element['#parents'];
    $element_name = array_shift($parents);
    $uploaded_files = \Drupal::request()->files->get('files', []);
    $uploaded_file = $uploaded_files[$element_name] ?? NULL;
    if ($uploaded_file) {
      // Cast this to an array so that the structure is consistent regardless of
      // whether #value is set or not.
      return (array) $uploaded_file;
    }
    return NULL;
  }
  public static function createInstance($input, $multiple, $process, $size, $pre_render, $theme, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('file');
    $instance->input = $input;
    $instance->multiple = $multiple;
    $instance->process = $process;
    $instance->size = $size;
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
      '#multiple' => $this->multiple,
      '#process' => $this->process,
      '#size' => $this->size,
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#theme_wrappers' => $this->theme_wrappers,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
