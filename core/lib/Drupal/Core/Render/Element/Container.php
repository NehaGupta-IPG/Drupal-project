<?php

namespace Drupal\Core\Render\Element;

use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element that wraps child elements in a container.
 *
 * Surrounds child elements with a <div> and adds attributes such as classes or
 * an HTML ID.
 *
 * Properties:
 * - #optional: Indicates whether the container should render when it has no
 *   visible children. Defaults to FALSE.
 *
 * Usage example:
 * @code
 * $form['needs_accommodation'] = [
 *   '#type' => 'checkbox',
 *   '#title' => $this->t('Need Special Accommodations?'),
 * ];
 *
 * $form['accommodation'] = [
 *   '#type' => 'container',
 *   '#attributes' => [
 *     'class' => ['accommodation'],
 *   ],
 *   '#states' => [
 *     'invisible' => [
 *       'input[name="needs_accommodation"]' => ['checked' => FALSE],
 *     ],
 *   ],
 * ];
 *
 * $form['accommodation']['diet'] = [
 *   '#type' => 'textfield',
 *   '#title' => $this->t('Dietary Restrictions'),
 * ];
 * @endcode
 */
#[RenderElement('container')]
class Container extends RenderElementBase {


  /**
   * @var mixed Automatically converted property
   */
  public $optional;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#optional' => FALSE,
      '#process' => [
        [static::class, 'processGroup'],
        [static::class, 'processContainer'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderGroup'],
        [static::class, 'preRenderContainer'],
      ],
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * Processes a container element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processContainer(&$element, FormStateInterface $form_state, &$complete_form) {
    // Generate the ID of the element if it's not explicitly given.
    if (!isset($element['#id'])) {
      $element['#id'] = HtmlUtility::getUniqueId(implode('-', $element['#parents']) . '-wrapper');
    }
    return $element;
  }

  /**
   * Prevents optional containers from rendering if they have no children.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   container.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderContainer($element) {
    // Do not render optional container elements if there are no children.
    if (empty($element['#printed']) && !empty($element['#optional']) && !Element::getVisibleChildren($element)) {
      $element['#printed'] = TRUE;
    }
    return $element;
  }

  public static function createInstance($optional, $process, $pre_render, $theme_wrappers, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('container');
    $instance->optional = $optional;
    $instance->process = $process;
    $instance->pre_render = $pre_render;
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
      '#optional' => $this->optional,
      '#process' => $this->process,
      '#pre_render' => $this->pre_render,
      '#theme_wrappers' => $this->theme_wrappers,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
