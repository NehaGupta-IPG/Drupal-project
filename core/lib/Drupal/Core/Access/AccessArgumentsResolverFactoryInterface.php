<?php

namespace Drupal\Core\Access;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Constructs the arguments resolver instance to use when running access checks.
 */
interface AccessArgumentsResolverFactoryInterface {

  /**
   * Returns the arguments resolver to use when running access checks.
   *
   * @param RouteMatchInterface $route_match
   *   The route match object to be checked.
   * @param AccountInterface $account
   *   The account being checked.
   * @param Request $request
   *   Optional, the request object.
   *
   * @return \Drupal\Component\Utility\ArgumentsResolverInterface
   *   The parametrized arguments resolver instance.
   */
  public function getArgumentsResolver(RouteMatchInterface $route_match, AccountInterface $account, ?Request $request = NULL);

}
