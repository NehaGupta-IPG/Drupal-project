<?php

declare(strict_types=1);

namespace Drupal\views\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Checks access to view routes based on argument validation.
 *
 * This is primarily used to avoid displaying links to views that will fail
 * validation.
 */
class ArgumentValidatorAccessCheck implements AccessInterface {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ViewExecutableFactory $executableFactory,
  ) {}

  /**
   * Checks whether a failed validation of an argument hides the view.
   *
   * @param array $options
   *   The argument handler options.
   *
   * @return bool
   *   TRUE if the argument is validated and a failure results in a "Page not
   *   found" or "Access denied" response.
   */
  public static function appliesToArgument(array $options): bool {
    return ($options['validate']['type'] ?? 'none') !== 'none'
      && in_array($options['validate']['fail'] ?? 'not found', ['not found', 'access denied'], TRUE);
  }

  /**
   * Checks access result for a view based on argument validation.
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
    // Only "Access denied" can be enforced on the incoming request, the view
    // responds with "Page not found" itself.
    if ($request !== NULL && !$route->getOption('_argument_validator_access_denied')) {
      return AccessResult::allowed();
    }

    $view = $this->entityTypeManager->getStorage('view')->load($view_id);
    $executable = $this->executableFactory->get($view);
    $executable->setDisplay($display_id);

    // @see \Drupal\views\Routing\ViewPageController::handle()
    $args = [];
    foreach ($route->getOption('_view_argument_map') ?? [] as $parameter_name) {
      $arg = $route_match->getRawParameter($parameter_name) ?: $route_match->getParameter($parameter_name);
      if ($arg !== NULL) {
        $args[] = $arg;
      }
    }

    // @see \Drupal\views\ViewExecutable::_buildArguments()
    $cacheability = CacheableMetadata::createFromObject($view);
    $position = 0;
    foreach ($executable->display_handler->getHandlers('argument') as $argument) {
      /** @var \Drupal\views\Plugin\views\argument\ArgumentPluginBase $argument */
      $arg = $args[$position++] ?? NULL;
      // Missing arguments trigger the default action, not validation.
      if ($arg === NULL) {
        break;
      }
      if ($argument->broken() || $argument->isException($arg)) {
        continue;
      }
      if (!$argument->getPlugin('argument_validator')->validateArgument($arg, $cacheability)) {
        $denied = $request === NULL ? static::appliesToArgument($argument->options) : $argument->options['validate']['fail'] === 'access denied';
        return AccessResult::allowedIf(!$denied)->addCacheableDependency($cacheability);
      }
    }

    return AccessResult::allowed()->addCacheableDependency($cacheability);
  }

}
