<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a matched path render element.
 *
 * Provides a form element to enter a path which can be optionally validated and
 * stored as either a \Drupal\Core\Url value object or an array containing a
 * route name and route parameters pair.
 */
#[FormElement('path')]
class PathElement extends Textfield {

  use ProcessPatternTrait;
  use ProcessAutocompleteTrait;

  /**
   * Automatically converted property.
   *
   * @var mixed
   */
  public $size;

  /**
   * Automatically converted property.
   *
   * @var mixed
   */
  public $maxlength;

  /**
   * Automatically converted property.
   *
   * @var mixed
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $validate_path;

  /**
   * Automatically converted property.
   *
   * @var mixed
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $convert_path;
  /**
   * Do not convert the submitted value from the user-supplied path.
   */
  const CONVERT_NONE = 0;

  /**
   * Convert the submitted value into a route name and parameter pair.
   */
  const CONVERT_ROUTE = 1;

  /**
   * Convert the submitted value into a \Drupal\Core\Url value object.
   */
  const CONVERT_URL = 2;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#validate_path'] = TRUE;
    $info['#convert_path'] = self::CONVERT_ROUTE;
    $info['#element_validate'] = [
      [static::class, 'validateMatchedPath'],
    ];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return NULL;
  }

  /**
   * Form element validation handler for matched_path elements.
   *
   * Note that #maxlength is validated by _form_validate() already.
   *
   * This checks that the submitted value matches an active route.
   */
  public static function validateMatchedPath(&$element, FormStateInterface $form_state, &$complete_form) {
    if (!empty($element['#value']) && ($element['#validate_path'] || $element['#convert_path'] != self::CONVERT_NONE)) {
      /** @var \Drupal\Core\Url $url */
      if ($url = \Drupal::service('path.validator')->getUrlIfValid($element['#value'])) {
        if ($url->isExternal()) {
          $form_state->setError($element, t('You cannot use an external URL. Enter a relative path.'));
          return;
        }
        if ($element['#convert_path'] == self::CONVERT_NONE) {
          // URL is valid, no conversion required.
          return;
        }
        // We do the value conversion here whilst the Url object is in scope
        // after validation has occurred.
        if ($element['#convert_path'] == self::CONVERT_ROUTE) {
          $form_state->setValueForElement($element, [
            'route_name' => $url->getRouteName(),
            'route_parameters' => $url->getRouteParameters(),
          ]);
          return;
        }
        elseif ($element['#convert_path'] == self::CONVERT_URL) {
          $form_state->setValueForElement($element, $url);
          return;
        }
      }
      $form_state->setError($element, t('This path does not exist or you do not have permission to link to %path.', [
        '%path' => $element['#value'],
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#input' => $this->input,
      '#size' => $this->size,
      '#maxlength' => $this->maxlength,
      '#autocomplete_route_name' => $this->autocomplete_route_name,
      '#process' => $this->process,
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#theme_wrappers' => $this->theme_wrappers,
      '#validate_path' => $this->validate_path,
      '#convert_path' => $this->convert_path,
      '#element_validate' => $this->element_validate,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
