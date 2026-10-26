<?php

declare(strict_types=1);

namespace Drupal\views\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
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
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  protected function handleUnsupportedRoute(): AccessResultInterface {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function handleMissingValue(CacheableMetadata $cacheability): AccessResultInterface {
    return AccessResult::forbidden()->addCacheableDependency($cacheability);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleInvalidValue(CacheableMetadata $cacheability): AccessResultInterface {
    return AccessResult::forbidden()->addCacheableDependency($cacheability);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleValidValues(CacheableMetadata $cacheability): AccessResultInterface {
    AccessResult::allowed()->addCacheableDependency($cacheability);
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
    return $this->runValidation($route, $route_match, $view_id, $display_id);
  }

}
