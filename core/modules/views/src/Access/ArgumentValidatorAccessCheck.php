<?php

declare(strict_types=1);

namespace Drupal\views\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\ViewsPluginManager;
use Symfony\Component\Routing\Route;

/**
 * Access check views with page or feed display and argument validation.
 *
 * This is primarily used to avoid displaying menu links for views that will
 * fail validation.
 */
class ArgumentValidatorAccessCheck implements AccessInterface {

  /**
   * The views plugin manager.
   *
   * @var \Drupal\views\Entity\ViewsPluginManager
   */
  protected $viewsPluginManager;

  /**
   * Route match for the current route.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructs an ArgumentValidatorAccessCheck instance.
   *
   * @param \Drupal\views\Entity\ViewsPluginManager $views_plugin_manager
   *   The views plugin manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   Route match for the current route.
   */
  public function __construct(ViewsPluginManager $views_plugin_manager, RouteMatchInterface $current_route_match) {
    $this->viewsPluginManager = $views_plugin_manager;
    $this->currentRouteMatch = $current_route_match;
  }

  /**
   * Checks access result for a view based on argument validation.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parameterized route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match) {
    $requirement = unserialize($route->getRequirement('_argument_validator_access'));

    if ($this->currentRouteMatch->getRouteName() === $route_match->getRouteName()) {
      // The route is the current route so let the view handle it.
      return AccessResult::allowed();
    }

    // We're on a different route. The result can affect whether menu links are
    // displayed or not.

    // Check validation for each argument by using its plugin.
    foreach ($requirement['arguments'] as $argument) {
      /** @var \Drupal\views\Plugin\views\PluginBase $plugin */
      $plugin = $this->viewsPluginManager->createInstance($argument['plugin_id']);

      // The view executable and handler are required for the plugin init
      // method. We probably need a more efficient way of doing this.

      $view = View::load($argument['view_id']);

      /** @var \Drupal\views\ViewExecutable $view_exe */
      $view_exe = $view->getExecutable();
      $view_exe->initHandlers();
      $handler = $view_exe->displayHandlers->get($argument['display_id']);

      $plugin->init($view_exe, $handler, $argument['plugin_options']);

      $parameter_value = $route_match->getRawParameter($argument['parameter_name']);
      if (!$plugin->validateArgument($parameter_value)) {
        return AccessResult::neutral();
      }
    }

    return AccessResult::allowed();
  }

}
