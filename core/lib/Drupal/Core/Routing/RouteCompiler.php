<?php

namespace Drupal\Core\Routing;

use Symfony\Component\Routing\RouteCompilerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCompiler as SymfonyRouteCompiler;

/**
 * Compiler to generate derived information from a Route necessary for matching.
 */
class RouteCompiler extends SymfonyRouteCompiler implements RouteCompilerInterface {

  /**
   * Value for a route's $num_parts indicating an unlimited number of parts.
   *
   * This is a positive integer rather than a special value (such as -1) so that
   * the query in \Drupal\Core\Routing\RouteProvider does not need to use an OR
   * condition when matching parts. Having an OR in the condition would degrade
   * performance.
   *
   * Any number of parts in a request will effectively match a route defined as
   * having this number of parts, because the {routing} table's 'path' field is
   * stored as a varchar(255). It's therefore impossible to have a route with
   * this many actual parts.
   */
  private const NUM_PARTS_UNLIMITED = 256;

  /**
   * Compiles the current route instance.
   *
   * Because so much of the parent class is private, we need to call the parent
   * class's compile() method and then dissect its return value to build our
   * new compiled object.  If upstream gets refactored so we can subclass more
   * easily then this may not be necessary.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   A Route instance.
   *
   * @return \Drupal\Core\Routing\CompiledRoute
   *   A CompiledRoute instance.
   */
  public static function compile(Route $route): CompiledRoute {
    // Symfony 4 requires that all UTF-8 route patterns have the "utf8" option
    // set and Drupal does not support non UTF-8 routes.
    $route->setOption('utf8', TRUE);
    $symfony_compiled = parent::compile($route);

    // The Drupal-specific compiled information.
    $stripped_path = static::getPathWithoutDefaults($route);
    $fit = static::getFit($stripped_path);
    $pattern_outline = static::getPatternOutline($stripped_path);
    // We count the number of parts including any optional trailing parts. This
    // allows the RouteProvider to filter candidate routes more efficiently.
    $parts = explode('/', trim($route->getPath(), '/'));
    $num_parts = count($parts);

    $unlimited_requirements = array_filter($route->getRequirements(), function ($it, $key) use ($parts) {
      if ($it !== '.*' && $it !== '.+') {
        return FALSE;
      }

      // Only consider requirements matching parameters in the path.
      $needle = "{{$key}}";
      return in_array($needle, $parts, TRUE);
    }, ARRAY_FILTER_USE_BOTH);

    if (count($unlimited_requirements) > 0) {
      $num_parts = static::NUM_PARTS_UNLIMITED;
    }

    return new CompiledRoute(
      $fit,
      $pattern_outline,
      $num_parts,

      // The following parameters are what Symfony uses in
      // \Symfony\Component\Routing\Matcher\UrlMatcher::matchCollection().

      // Set the static prefix to an empty string since it is redundant to
      // the matching in \Drupal\Core\Routing\RouteProvider::getRoutesByPath()
      // and by skipping it we more easily make the routing case-insensitive.
      '',
      $symfony_compiled->getRegex(),
      $symfony_compiled->getTokens(),
      $symfony_compiled->getPathVariables(),
      $symfony_compiled->getHostRegex(),
      $symfony_compiled->getHostTokens(),
      $symfony_compiled->getHostVariables(),
      $symfony_compiled->getVariables()
    );
  }

  /**
   * Returns the pattern outline.
   *
   * The pattern outline is the path pattern but normalized so that all
   * placeholders are the string '%'.
   *
   * @param string $path
   *   The path for which we want the normalized outline.
   *
   * @return string
   *   The path pattern outline.
   */
  public static function getPatternOutline($path) {
    return preg_replace('#\{\w+\}#', '%', $path);
  }

  /**
   * Determines the fitness of the provided path.
   *
   * @param string $path
   *   The path whose fitness we want.
   *
   * @return int
   *   The fitness of the path, as an integer.
   */
  public static function getFit($path) {
    $parts = explode('/', trim($path, '/'));
    $number_parts = count($parts);
    // We store the highest index of parts here to save some work in the fit
    // calculation loop.
    $slashes = $number_parts - 1;
    // The fit value is a binary number which has 1 at every fixed path
    // position and 0 where there is a wildcard. We keep track of all such
    // patterns that exist so that we can minimize the number of path
    // patterns we need to check in the RouteProvider.
    $fit = 0;
    foreach ($parts as $k => $part) {
      if (!str_contains($part, '{')) {
        $fit |= 1 << ($slashes - $k);
      }
    }

    return $fit;
  }

  /**
   * Returns the path of the route, without placeholders with a default value.
   *
   * When computing the path outline and fit, we want to skip default-value
   * placeholders.  If we didn't, the path would never match.  Note that this
   * only works for placeholders at the end of the path. Infix placeholders
   * with default values don't make sense anyway, so that should not be a
   * problem.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to have the placeholders removed from.
   *
   * @return string
   *   The path string, stripped of placeholders that have default values.
   */
  public static function getPathWithoutDefaults(Route $route) {
    $path = $route->getPath();
    $defaults = $route->getDefaults();

    // Remove placeholders with default values from the outline, so that they
    // will still match.
    $remove = array_map(function ($a) {
      return '/{' . $a . '}';
    }, array_keys($defaults));
    $path = str_replace($remove, '', $path);

    return $path;
  }

}
