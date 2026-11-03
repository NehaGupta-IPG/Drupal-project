<?php

namespace Drupal\views\Routing;

use Drupal\Component\Utility\Xss;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\ContextualLinksHelper;
use Drupal\views\Plugin\views\display\Page;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\Views;

/**
 * Defines a page controller to execute and render a view.
 */
class ViewPageController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected readonly ContextualLinksHelper $contextualLinks,
  ) {}

  /**
   * Handler a response for a given view and display.
   *
   * @param string $view_id
   *   The ID of the view.
   * @param string $display_id
   *   The ID of the display.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A render array or a Response object.
   */
  public function handle($view_id, $display_id, RouteMatchInterface $route_match) {
    $args = Views::buildArgs($route_match);
    $route = $route_match->getRouteObject();

    $class = $route->getOption('_view_display_plugin_class');
    if ($route->getOption('returns_response')) {
      /** @var \Drupal\views\Plugin\views\display\ResponseDisplayPluginInterface $class */
      return $class::buildResponse($view_id, $display_id, $args);
    }
    else {
      /** @var \Drupal\views\Plugin\views\display\Page $class */
      $build = $class::buildBasicRenderable($view_id, $display_id, $args, $route);
      Page::setPageRenderArray($build);

      $this->contextualLinks->addLinks($build, 'page', $display_id, $build);

      return $build;
    }
  }

  /**
   * Gets the title of the given view's display.
   *
   * @param string $view_id
   *   The id of the view.
   * @param string $display_id
   *   The id of the display from the view.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The title of the display of the view.
   */
  public function getTitle($view_id, $display_id = 'default') {
    $view = Views::getView($view_id);
    $view->setDisplay($display_id);

    return ViewsRenderPipelineMarkup::create(Xss::filter($view->getTitle()));
  }

}
