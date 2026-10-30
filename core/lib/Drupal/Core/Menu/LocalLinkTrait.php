<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a trait for a common implementation of local links.
 *
 * @see \Drupal\Core\Menu\LocalLinkInterface
 */
trait LocalLinkTrait {

  /**
   * The plugin implementation definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * The route provider to load routes by name.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return $this->pluginDefinition['route_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $route_parameters = $this->pluginDefinition['route_parameters'] ?? [];
    $route = $this->routeProvider()->getRouteByName($this->getRouteName());
    $variables = $route->compile()->getVariables();

    // Normally the \Drupal\Core\ParamConverter\ParamConverterManager has
    // run, and the route parameters have been upcast. The original values can
    // be retrieved from the raw parameters. For example, if the route's path is
    // /filter/tips/{filter_format} and the path is /filter/tips/plain_text then
    // $raw_parameters->get('filter_format') == 'plain_text'. Parameters that
    // are not represented in the route path as slugs might be added by a route
    // enhancer and will not be present in the raw parameters.
    $raw_parameters = $route_match->getRawParameters();
    $parameters = $route_match->getParameters();

    foreach ($variables as $name) {
      if (isset($route_parameters[$name])) {
        continue;
      }

      if ($raw_parameters->has($name)) {
        $route_parameters[$name] = $raw_parameters->get($name);
      }
      elseif ($parameters->has($name)) {
        $route_parameters[$name] = $parameters->get($name);
      }
    }

    // The UrlGenerator will throw an exception if expected parameters are
    // missing. This method should be overridden if that is possible.
    return $route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    // Subclasses may pull in the request or specific attributes as parameters.
    // The title from YAML file discovery may be a TranslatableMarkup object.
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return (int) $this->pluginDefinition['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    return (array) $this->pluginDefinition['options'];
  }

  /**
   * Returns the route provider.
   *
   * @return \Drupal\Core\Routing\RouteProviderInterface
   *   The route provider.
   */
  protected function routeProvider() {
    if (!$this->routeProvider) {
      $this->routeProvider = \Drupal::service('router.route_provider');
    }
    return $this->routeProvider;
  }

}
