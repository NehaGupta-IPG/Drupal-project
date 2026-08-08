<?php

namespace Drupal\Core\Access;

use Drupal\Component\Utility\ArgumentsResolver;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use function Symfony\Component\String\u;

/**
 * Resolves the arguments to pass to an access check callable.
 */
readonly class AccessArgumentsResolverFactory implements AccessArgumentsResolverFactoryInterface {

  public function __construct(protected LanguageManagerInterface $languageManager) {}

  /**
   * {@inheritdoc}
   */
  public function getArgumentsResolver(RouteMatchInterface $route_match, AccountInterface $account, ?Request $request = NULL) {
    $route = $route_match->getRouteObject();

    // Defaults for the parameters defined on the route object need to be added
    // to the raw arguments.
    $raw_route_arguments = $route_match->getRawParameters()->all() + $route->getDefaults();

    $upcasted_route_arguments = $route_match->getParameters()->all();

    // Provide language objects per available language type. Use matching route
    // parameter or default ('language_content_', 'language_interface_', etc.)
    // values if provided, otherwise get the current languages from the language
    // manager. Note that the default or parameter name is the language type
    // with an underscore appended to it. This naming is used to make the chance
    // of a collision with other parameters or defaults defined on a route.
    foreach ($this->languageManager->getDefinedLanguageTypes() as $type) {
      $param_name = $type . '_';
      $language = isset($raw_route_arguments[$param_name]) ? $this->languageManager->getLanguage($raw_route_arguments[$param_name]) : $this->languageManager->getCurrentLanguage($type);
      // Match arguments for both snake- and camel-cased names.
      $upcasted_route_arguments[$type] = $language;
      $upcasted_route_arguments[(string) u($type)->camel()] = $language;
    }

    // Parameters which are not defined on the route object, but still are
    // essential for access checking are passed as wildcards to the argument
    // resolver. An access-check method with a parameter of type Route,
    // RouteMatchInterface, AccountInterface or Request will receive those
    // arguments regardless of the parameter name.
    $wildcard_arguments = [$route, $route_match, $account];
    if (isset($request)) {
      $wildcard_arguments[] = $request;
    }

    return new ArgumentsResolver($raw_route_arguments, $upcasted_route_arguments, $wildcard_arguments);
  }

}
