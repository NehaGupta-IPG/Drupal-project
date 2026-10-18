<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;

/**
 * Provides a form element for a submit button with an image.
 */
#[FormElement('image_button')]
class ImageButton extends Submit {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    unset($info['name']);

    return [
      '#return_value' => TRUE,
      '#has_garbage_value' => TRUE,
      '#src' => NULL,
      '#theme_wrappers' => ['input__image_button'],
    ] + $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      if (!empty($input)) {
        return $element['#return_value'];
      }
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderButton($element) {
    $element['#attributes']['type'] = 'image';
    Element::setAttributes($element, ['id', 'name', 'value']);

    $element['#attributes']['src'] = \Drupal::service('file_url_generator')->generateString($element['#src']);
    if (!empty($element['#title'])) {
      $element['#attributes']['alt'] = $element['#title'];
      $element['#attributes']['title'] = $element['#title'];
    }

    $element['#attributes']['class'][] = 'image-button';
    if (!empty($element['#button_type'])) {
      $element['#attributes']['class'][] = 'image-button--' . $element['#button_type'];
    }
    $element['#attributes']['class'][] = 'js-form-submit';
    $element['#attributes']['class'][] = 'form-submit';

    if (!empty($element['#attributes']['disabled'])) {
      $element['#attributes']['class'][] = 'is-disabled';
    }

    return $element;
  }

}
