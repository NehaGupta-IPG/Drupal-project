<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Defines an interface for local links.
 *
 * Examples of local links are local actions and local tasks. They often use
 * route parameters that are the same as the current page.
 *
 * @see \Drupal\Core\Menu\LocalLinkTrait
 */
interface LocalLinkInterface {

  /**
   * Get the route name from the settings.
   *
   * @return string
   *   The name of the route this action links to.
   */
  public function getRouteName();

  /**
   * Returns the route parameters needed to render a link for the local action.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   *
   * @return array
   *   An array of parameter names and values.
   */
  public function getRouteParameters(RouteMatchInterface $route_match);

  /**
   * Returns the weight for the local action.
   *
   * @return int
   *   The weight of this action.
   */
  public function getWeight();

  /**
   * Returns options for rendering a link for the local action.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   *
   * @return array
   *   An associative array of options.
   */
  public function getOptions(RouteMatchInterface $route_match);

  /**
   * Returns the localized title to be shown for this action.
   *
   * Subclasses may add optional arguments like NodeInterface $node = NULL that
   * will be supplied by the ControllerResolver.
   *
   * @return string
   *   The title to be shown for this action.
   *
   * @see \Drupal\Core\Menu\LocalActionManager::getTitle()
   */
  public function getTitle();

}
