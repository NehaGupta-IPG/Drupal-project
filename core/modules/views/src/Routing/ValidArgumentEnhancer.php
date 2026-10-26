<?php

namespace Drupal\views\Routing;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\views\ValidArgumentBase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Checks view routes based on argument configuration.
 *
 * This is primarily used to avoid displaying links to views that will return a
 * 404 response.
 */
class ValidArgumentEnhancer extends ValidArgumentBase implements EnhancerInterface {

  /**
   * {@inheritdoc}
   */
  public static function requiresValue(array $options): bool {
    return ($options['default_action'] ?? '') === 'not found';
  }

  /**
   * {@inheritdoc}
   */
  public static function requiresValidation(array $options): bool {
    if (!isset($options['validate']['type']) || $options['validate']['type'] === 'none') {
      return FALSE;
    }
    // Default behavior is "not found".
    return !isset($options['validate']['fail']) || $options['validate']['fail'] === 'not found';
  }

  /**
   * {@inheritdoc}
   */
  protected function getArgumentsToCheck(Route $route): array {
    if (!$route->hasOption('_views_argument_enhancer')) {
      return [];
    }

    $argument_list = $route->getOption('_views_argument_enhancer');
    if ($argument_list === '') {
      return [];
    }

    return explode(',', $argument_list);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleUnsupportedRoute(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function handleMissingValue(CacheableMetadata $cacheability): array {
    throw new CacheableNotFoundHttpException($cacheability);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleInvalidValue(CacheableMetadata $cacheability): array {
    throw new CacheableNotFoundHttpException($cacheability);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleValidValues(CacheableMetadata $cacheability): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    assert($route instanceof Route);

    $raw = $defaults['_raw_variables'];
    assert($raw instanceof InputBag);

    $view_id = $route->getDefault('view_id');
    $display_id = $route->getDefault('display_id');

    // We disregard the return value of the parent function, as we do not do any
    // actual converting. Any exceptions short-circuit the logic, so we can
    // always return the defaults otherwise.
    if (is_string($view_id) && is_string($display_id)) {
      $route_match = new RouteMatch($defaults[RouteObjectInterface::ROUTE_NAME], $route, $defaults, $raw->all());
      $this->runValidation($route, $route_match, $view_id, $display_id);
    }
    return $defaults;
  }

}
