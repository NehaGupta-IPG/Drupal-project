<?php

declare(strict_types=1);

namespace Drupal\views\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\ValidArgumentBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Checks access to view routes based on argument configuration.
 *
 * This is primarily used to avoid displaying links to views that will return a
 * 403 response.
 */
class ValidArgumentAccessCheck extends ValidArgumentBase implements AccessInterface {

  /**
   * {@inheritdoc}
   */
  public static function requiresValue(array $options): bool {
    return ($options['default_action'] ?? '') === 'access denied';
  }

  /**
   * {@inheritdoc}
   */
  public static function requiresValidation(array $options): bool {
    if (!isset($options['validate']['type']) || $options['validate']['type'] === 'none') {
      return FALSE;
    }
    return ($options['validate']['fail'] ?? '') === 'access denied';
  }

  /**
   * Gets the arguments to check from the route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to get the arguments from.
   *
   * @return array
   *   The arguments to check.
   */
  protected function getArgumentsToCheck(Route $route): array {
    if (!$route->hasRequirement('_views_argument_access')) {
      return [];
    }

    $argument_list = $route->getRequirement('_views_argument_access');
    if ($argument_list === '') {
      return [];
    }

    return explode(',', $argument_list);
  }

  /**
   * Checks access result for a view based on argument configuration.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parameterized route.
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The display ID.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The incoming request, only passed when the route is being routed to.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, string $view_id, string $display_id, ?Request $request = NULL): AccessResultInterface {
    $argument_map = $route->getOption('_view_argument_map') ?? [];
    if ($argument_map === []) {
      return AccessResult::allowed();
    }

    $arguments_to_check = $this->getArgumentsToCheck($route);
    if ($arguments_to_check === []) {
      return AccessResult::allowed();
    }

    $view = $this->loadViewExecutable($view_id, $display_id);
    $cacheability = CacheableMetadata::createFromObject($view);

    $argument_counter = 0;
    foreach ($view->getDisplay()->getHandlers('argument') as $name => $argument) {
      assert($argument instanceof ArgumentPluginBase);
      $argument_id = 'arg_' . $argument_counter++;

      if (!in_array($name, $arguments_to_check, TRUE)) {
        continue;
      }

      $parameter_name = $argument_map[$argument_id] ?? FALSE;
      if ($parameter_name === FALSE) {
        continue;
      }

      $argument_value = $route_match->getRawParameter($parameter_name);
      if (!$this->argumentValueIsValid($argument, $argument_value, $cacheability)) {
        return AccessResult::forbidden()->addCacheableDependency($cacheability);
      }
    }

    return AccessResult::allowed()->addCacheableDependency($cacheability);
  }

}
