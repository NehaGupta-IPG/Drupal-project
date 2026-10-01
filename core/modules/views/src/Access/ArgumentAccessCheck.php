<?php

declare(strict_types=1);

namespace Drupal\views\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\Exception\CacheableResourceNotFoundException;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Checks access to view routes based on argument configuration.
 *
 * This is primarily used to avoid displaying links to views that will either
 * return a 403 or 404 response.
 */
class ArgumentAccessCheck implements AccessInterface {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ViewExecutableFactory $executableFactory,
  ) {}

  /**
   * Checks whether a NULL argument will return a 403 or 404 response.
   *
   * @param array $options
   *   The argument handler options.
   *
   * @return bool
   *   Whether a NULL argument will return a 403 or 404 response.
   */
  public static function requiresValue(array $options): bool {
    return in_array($options['default_action'] ?? 'default', ['not found', 'access denied'], TRUE);
  }

  /**
   * Checks whether an invalid argument will return a 403 or 404 response.
   *
   * @param array $options
   *   The argument handler options.
   *
   * @return bool
   *   Whether an invalid argument will return a 403 or 404 response.
   */
  public static function requiresValidation(array $options): bool {
    return ($options['validate']['type'] ?? 'none') !== 'none'
      && in_array($options['validate']['fail'] ?? 'not found', ['not found', 'access denied'], TRUE);
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
    $arguments_to_check = explode(',', $route->getRequirement('_views_argument_access'));
    if (empty($arguments_to_check)) {
      return AccessResult::allowed();
    }

    $view = $this->entityTypeManager->getStorage('view')->load($view_id);
    $executable = $this->executableFactory->get($view);
    $executable->setDisplay($display_id);
    $cacheability = CacheableMetadata::createFromObject($view);

    $argument_map = $route->getOption('_view_argument_map') ?? [];
    $argument_counter = 0;
    foreach ($executable->display_handler->getHandlers('argument') as $name => $argument) {
      $argument_id = 'arg_' . $argument_counter++;
      if (!in_array($name, $arguments_to_check, TRUE)) {
        continue;
      }
      if (!isset($argument_map[$argument_id])) {
        continue;
      }

      $argument_options = $argument->options ?? [];
      $requires_value = self::requiresValue($argument_options);
      $requires_validation = self::requiresValidation($argument_options);
      if (!$requires_value && !$requires_validation) {
        continue;
      }

      $parameter_name = $argument_map[$argument_id];
      $argument_value = $route_match->getRawParameter($parameter_name) ?: $route_match->getParameter($parameter_name);

      if ($requires_value && $argument_value === NULL) {
        if ($argument_options['default_action'] === 'not found') {
          throw new CacheableResourceNotFoundException($cacheability);
        }
        return AccessResult::forbidden()->addCacheableDependency($cacheability);
      }

      assert($argument instanceof ArgumentPluginBase);
      if ($argument->broken() || $argument->isException($argument_value)) {
        continue;
      }

      if ($requires_validation && !$argument->getPlugin('argument_validator')->validateArgument($argument_value, $cacheability)) {
        if ($argument_options['validate']['fail'] === 'not found') {
          throw new CacheableResourceNotFoundException($cacheability);
        }
        return AccessResult::forbidden()->addCacheableDependency($cacheability);
      }
    }

    return AccessResult::allowed()->addCacheableDependency($cacheability);
  }

}
