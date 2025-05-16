<?php

namespace Drupal\contextual\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Url;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a contextual_links element.
 */
#[RenderElement('contextual_links')]
class ContextualLinks extends RenderElementBase {


  /**
   * @var mixed Automatically converted property
   */
  public $links;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderLinks'],
      ],
      '#theme' => 'links__contextual',
      '#links' => [],
      '#attributes' => ['class' => ['contextual-links']],
      '#attached' => [
        'library' => [
          'contextual/drupal.contextual-links',
        ],
      ],
    ];
  }

  /**
   * Pre-render callback: Builds a renderable array for contextual links.
   *
   * @param array $element
   *   A renderable array containing a #contextual_links property, which is a
   *   keyed array. Each key is the name of the group of contextual links to
   *   render (based on the 'group' key in the *.links.contextual.yml files for
   *   all enabled modules). The value contains an associative array containing
   *   the following keys:
   *   - route_parameters: The route parameters passed to the URL generator.
   *   - metadata: Any additional data needed in order to alter the link.
   *   @code
   *     ['#contextual_links' => [
   *       'block' => [
   *         'route_parameters' => ['block' => 'system.menu-tools'],
   *       ],
   *       'menu' => [
   *         'route_parameters' => ['menu' => 'tools'],
   *       ],
   *     ]]
   *   @endcode
   *
   * @return array
   *   A renderable array representing contextual links.
   */
  public static function preRenderLinks(array $element) {
    // Retrieve contextual menu links.
    $items = [];

    $contextual_links_manager = static::contextualLinkManager();

    foreach ($element['#contextual_links'] as $group => $args) {
      $args += [
        'route_parameters' => [],
        'metadata' => [],
      ];
      $items += $contextual_links_manager->getContextualLinksArrayByGroup($group, $args['route_parameters'], $args['metadata']);
    }

    uasort($items, [SortArray::class, 'sortByWeightElement']);

    // Transform contextual links into parameters suitable for links.html.twig.
    $links = [];
    foreach ($items as $class => $item) {
      $class = Html::getClass($class);
      $links[$class] = [
        'title' => $item['title'],
        'url' => Url::fromRoute($item['route_name'] ?? '', $item['route_parameters'] ?? [], $item['localized_options']),
      ];
    }
    $element['#links'] = $links;

    // Allow modules to alter the renderable contextual links element.
    static::moduleHandler()->alter('contextual_links_view', $element, $items);

    // If there are no links, tell \Drupal::service('renderer')->render() to
    // abort rendering.
    if (empty($element['#links'])) {
      $element['#printed'] = TRUE;
    }

    return $element;
  }

  /**
   * Wraps the contextual link manager.
   *
   * @return \Drupal\Core\Menu\ContextualLinkManager
   *   The contextual link manager service.
   */
  protected static function contextualLinkManager() {
    return \Drupal::service('plugin.manager.menu.contextual_link');
  }

  /**
   * Wraps the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   */
  protected static function moduleHandler() {
    return \Drupal::moduleHandler();
  }

  public static function createInstance($pre_render, $theme, $links, $attributes, $attached, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('contextual_links');
    $instance->pre_render = $pre_render;
    $instance->theme = $theme;
    $instance->links = $links;
    $instance->attributes = $attributes;
    $instance->attached = $attached;
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
      '#links' => $this->links,
      '#attributes' => $this->attributes,
      '#attached' => $this->attached,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
