<?php

namespace Drupal\jsonapi\Routing;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\FilterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Filters routes based on the HTTP method and JSON:API's read-only mode.
 *
 * Write operations (POST, PATCH, DELETE) are only available when the
 * jsonapi_write module is installed.
 */
class ReadOnlyModeMethodFilter implements FilterInterface {

  public function __construct(
    protected FilterInterface $inner,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function filter(RouteCollection $collection, Request $request) {
    $all_supported_methods = [];
    foreach ($collection->all() as $name => $route) {
      $all_supported_methods[] = $route->getMethods();
    }

    $all_supported_methods = array_merge(...$all_supported_methods);
    $collection = $this->inner->filter($collection, $request);

    if ($this->moduleHandler->moduleExists('jsonapi_write')) {
      return $collection;
    }

    $read_only_methods = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];
    foreach ($collection->all() as $name => $route) {
      if (!$route->hasDefault(Routes::JSON_API_ROUTE_FLAG_KEY)) {
        continue;
      }

      $supported_methods = $route->getMethods();
      assert(count($supported_methods) > 0, 'JSON:API routes always have a method specified.');
      $is_read_only_route = empty(array_diff($supported_methods, $read_only_methods));
      if (!$is_read_only_route) {
        $collection->remove($name);
      }
    }
    if (count($collection)) {
      return $collection;
    }
    throw new MethodNotAllowedHttpException(array_intersect($all_supported_methods, $read_only_methods), 'JSON:API is configured to accept only read operations. Install the JSON:API Write module to enable write operations.');
  }

}
