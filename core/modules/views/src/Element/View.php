<?php

namespace Drupal\views\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\views\Exception\ViewRenderElementException;
use Drupal\views\Views;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element to display a view.
 */
#[RenderElement('view')]
class View extends RenderElementBase {


  /**
   * @var mixed Automatically converted property
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $display_id;

  /**
   * @var mixed Automatically converted property
   */
  public $arguments;

  /**
   * @var mixed Automatically converted property
   */
  public $embed;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderViewElement'],
      ],
      '#name' => NULL,
      '#display_id' => 'default',
      '#arguments' => [],
      '#embed' => TRUE,
      '#cache' => [],
    ];
  }

  /**
   * View element pre render callback.
   */
  public static function preRenderViewElement($element) {
    // Allow specific Views displays to explicitly perform pre-rendering, for
    // those displays that need to be able to know the fully built render array.
    if (!empty($element['#pre_rendered'])) {
      return $element;
    }

    if (!isset($element['#view'])) {
      $view = Views::getView($element['#name']);
      if (!$view) {
        throw new ViewRenderElementException("Invalid View name ({$element['#name']}) given.");
      }
    }
    else {
      $view = $element['#view'];
    }

    $element += $view->element;
    $view->element = &$element;
    // Mark the element as being prerendered, so other code like
    // \Drupal\views\ViewExecutable::setCurrentPage knows that its no longer
    // possible to manipulate the $element.
    $view->element['#pre_rendered'] = TRUE;

    if (isset($element['#response'])) {
      $view->setResponse($element['#response']);
    }

    if ($view && $view->access($element['#display_id'])) {
      if (!empty($element['#embed'])) {
        $element['view_build'] = $view->preview($element['#display_id'], $element['#arguments']);
      }
      else {
        // Add contextual links to the view. We need to attach them to the dummy
        // $view_array variable, since contextual_preprocess() requires that
        // they be attached to an array (not an object) in order to process
        // them. For our purposes, it doesn't matter what we attach them to,
        // since once they are processed by contextual_preprocess() they will
        // appear in the $title_suffix variable (which we will then render in
        // views-view.html.twig).
        $view->setDisplay($element['#display_id']);
        // Add the result of the executed view as a child element so any
        // #pre_render elements for the view will get processed. A #pre_render
        // element cannot be added to the main element as this is already inside
        // a #pre_render callback.
        $element['view_build'] = $view->executeDisplay($element['#display_id'], $element['#arguments']);

        if (isset($element['view_build']['#title'])) {
          $element['#title'] = &$element['view_build']['#title'];
        }

        if (empty($view->display_handler->getPluginDefinition()['returns_response'])) {
          // views_add_contextual_links() needs the following information in
          // order to be attached to the view.
          $element['#view_id'] = $view->storage->id();
          $element['#view_display_show_admin_links'] = $view->getShowAdminLinks();
          $element['#view_display_plugin_id'] = $view->display_handler->getPluginId();
          views_add_contextual_links($element, 'view', $view->current_display);
        }
      }
      if (empty($view->display_handler->getPluginDefinition()['returns_response'])) {
        $element['#attributes']['class'][] = 'views-element-container';
        $element['#theme_wrappers'] = ['container'];
      }
    }

    return $element;
  }

  /**
   * Create a view element.
   *
   * @param mixed $pre_render
   *   Automatically converted param.
   * @param mixed $name
   *   Automatically converted param.
   * @param mixed $display_id
   *   Automatically converted param.
   * @param mixed $arguments
   *   Automatically converted param.
   * @param mixed $embed
   *   Automatically converted param.
   * @param mixed $cache
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
  public static function createInstance($pre_render, $name, $display_id, $arguments, $embed, $cache, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('view');
    $instance->pre_render = $pre_render;
    $instance->name = $name;
    $instance->display_id = $display_id;
    $instance->arguments = $arguments;
    $instance->embed = $embed;
    $instance->cache = $cache;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#pre_render' => $this->pre_render,
      '#name' => $this->name,
      '#display_id' => $this->display_id,
      '#arguments' => $this->arguments,
      '#embed' => $this->embed,
      '#cache' => $this->cache,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
