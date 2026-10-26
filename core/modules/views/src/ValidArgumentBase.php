<?php

declare(strict_types=1);

namespace Drupal\views;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Provides reusable code to be shared by Views argument validation.
 *
 * Because invalid arguments can lead to either 403 or 404 responses, but both
 * need to be enforced in different places (access check and route enhancer), we
 * try to keep the shared code running the checks here.
 */
abstract class ValidArgumentBase {

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
  abstract public static function requiresValue(array $options): bool;

  /**
   * Checks whether an invalid argument will return a 403 or 404 response.
   *
   * @param array $options
   *   The argument handler options.
   *
   * @return bool
   *   Whether an invalid argument will return a 403 or 404 response.
   */
  abstract public static function requiresValidation(array $options): bool;

  /**
   * Gets the arguments to check from the route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *
   * @return []
   *   The arguments to check.
   */
  abstract protected function getArgumentsToCheck(Route $route): array;

  /**
   * What to do when the route does not contain the metadata we need.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|array
   *   The expected return value for the implementer, which is an access result
   *   for access checks and an array for param converters.
   */
  abstract protected function handleUnsupportedRoute(): AccessResultInterface|array;

  /**
   * What to do when the value is required but missing.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   The cacheable metadata of the checks that have run so far.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|array
   *   The expected return value for the implementer, which is an access result
   *   for access checks and an array for param converters.
   */
  abstract protected function handleMissingValue(CacheableMetadata $cacheability): AccessResultInterface|array;

  /**
   * What to do when the value requires validation but is invalid.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   The cacheable metadata of the checks that have run so far.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|array
   *   The expected return value for the implementer, which is an access result
   *   for access checks and an array for param converters.
   */
  abstract protected function handleInvalidValue(CacheableMetadata $cacheability): AccessResultInterface|array;

  /**
   * What to do when the values were present, valid or required no special care.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   The cacheable metadata of the checks that have run so far.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|array
   *   The expected return value for the implementer, which is an access result
   *   for access checks and an array for param converters.
   */
  abstract protected function handleValidValues(CacheableMetadata $cacheability): AccessResultInterface|array;

  /**
   * Runs the view argument validation for a route and route match.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parameterized route.
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The display ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|array
   *   The return value, which is an access result for access checks and an
   *   array for param converters.
   */
  protected function runValidation(Route $route, RouteMatchInterface $route_match, string $view_id, string $display_id): AccessResultInterface|array {
    $arguments_to_check = $this->getArgumentsToCheck($route);
    if ($arguments_to_check === []) {
      return $this->handleUnsupportedRoute();
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
        return $this->handleMissingValue($cacheability);
      }

      assert($argument instanceof ArgumentPluginBase);
      if ($argument->broken() || $argument->isException($argument_value)) {
        continue;
      }

      if ($requires_validation && !$argument->getPlugin('argument_validator')->validateArgument($argument_value, $cacheability)) {
        return $this->handleInvalidValue($cacheability);
      }
    }

    return $this->handleValidValues($cacheability);
  }

}
