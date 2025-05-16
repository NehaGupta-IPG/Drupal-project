<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a render element for a pager.
 *
 * The pager must be initialized with a call to
 * \Drupal\Core\Pager\PagerManagerInterface::createPager() in order to render
 * properly. When used with database queries, this is performed for you when you
 * extend a select query with \Drupal\Core\Database\Query\PagerSelectExtender.
 *
 * Properties:
 * - #element: (optional, int) The pager ID, to distinguish between multiple
 *   pagers on the same page (defaults to 0).
 * - #pagination_heading_level: (optional) A heading level for the pager.
 * - #parameters: (optional) An associative array of query string parameters to
 *   append to the pager.
 * - #quantity: The maximum number of numbered page links to create (defaults
 *   to 9).
 * - #tags: (optional) An array of labels for the controls in the pages.
 * - #route_name: (optional) The name of the route to be used to build pager
 *   links. Defaults to '<none>', which will make links relative to the current
 *   URL. This makes the page more effectively cacheable.
 *
 * @code
 * $build['pager'] = [
 *   '#type' => 'pager',
 * ];
 * @endcode
 */
#[RenderElement('pager')]
class Pager extends RenderElementBase {

  
  /**
   * @var mixed Automatically converted property
   */
  public $element;
  
  /**
   * @var mixed Automatically converted property
   */
  public $pagination_heading_level;
  
  /**
   * @var mixed Automatically converted property
   */
  public $parameters;
  
  /**
   * @var mixed Automatically converted property
   */
  public $quantity;
  
  /**
   * @var mixed Automatically converted property
   */
  public $tags;
  
  /**
   * @var mixed Automatically converted property
   */
  public $route_name;
  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        static::class . '::preRenderPager',
      ],
      '#theme' => 'pager',
      // The pager ID, to distinguish between multiple pagers on the same page.
      '#element' => 0,
      // The heading level to use for the pager.
      '#pagination_heading_level' => 'h4',
      // An associative array of query string parameters to append to the pager
      // links.
      '#parameters' => [],
      // The number of pages in the list.
      '#quantity' => 9,
      // An array of labels for the controls in the pager.
      '#tags' => [],
      // The name of the route to be used to build pager links. By default no
      // path is provided, which will make links relative to the current URL.
      // This makes the page more effectively cacheable.
      '#route_name' => '<none>',
    ];
  }

  /**
   * Render API callback: Associates the appropriate cache context.
   *
   * This function is assigned as a #pre_render callback.
   *
   * @param array $pager
   *   A renderable array of #type => pager.
   *
   * @return array
   *   The render array with cache contexts added.
   */
  public static function preRenderPager(array $pager) {
    // Note: the default pager theme process function
    // template_preprocess_pager() also calls
    // \Drupal\Core\Pager\PagerManagerInterface::getUpdatedParameters(), which
    // maintains the existing query string. Therefore
    // template_preprocess_pager() adds the 'url.query_args' cache context,
    // which causes the more specific cache context below to be optimized away.
    // In other themes, however, that may not be the case.
    $pager['#cache']['contexts'][] = 'url.query_args.pagers:' . $pager['#element'];
    return $pager;
  }
  public static function createInstance($pre_render, $theme, $element, $pagination_heading_level, $parameters, $quantity, $tags, $route_name, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('pager');
    $instance->pre_render = $pre_render;
    $instance->theme = $theme;
    $instance->element = $element;
    $instance->pagination_heading_level = $pagination_heading_level;
    $instance->parameters = $parameters;
    $instance->quantity = $quantity;
    $instance->tags = $tags;
    $instance->route_name = $route_name;
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
      '#theme' => $this->theme,
      '#element' => $this->element,
      '#pagination_heading_level' => $this->pagination_heading_level,
      '#parameters' => $this->parameters,
      '#quantity' => $this->quantity,
      '#tags' => $this->tags,
      '#route_name' => $this->route_name,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
