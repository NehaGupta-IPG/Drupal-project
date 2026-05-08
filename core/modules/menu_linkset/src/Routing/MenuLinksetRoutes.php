<?php

namespace Drupal\menu_linkset\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dynamically applies authentication providers to the menu linkset route.
 *
 * The set of available authentication providers cannot be known when the route
 * is statically declared, so the route's `_auth` option is populated here.
 */
class MenuLinksetRoutes extends RouteSubscriberBase {

  /**
   * An array of enabled authentication provider IDs.
   *
   * @var string[]
   */
  protected readonly array $providerIds;

  /**
   * Constructs a MenuLinksetRoutes object.
   *
   * @param string[] $authenticationProviders
   *   An array of authentication providers, keyed by ID.
   */
  public function __construct(
    #[Autowire(param: 'authentication_providers')]
    array $authenticationProviders,
  ) {
    $this->providerIds = array_keys($authenticationProviders);
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('menu_linkset.linkset')) {
      $route->setOption('_auth', $this->providerIds);
    }
  }

}
