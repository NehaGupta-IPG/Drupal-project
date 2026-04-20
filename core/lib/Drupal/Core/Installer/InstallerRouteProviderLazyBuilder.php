<?php

namespace Drupal\Core\Installer;

use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\RouteProviderLazyBuilder;
use Symfony\Component\Routing\Route;

/**
 * A Route Provider front-end for use during the installer.
 */
class InstallerRouteProviderLazyBuilder extends RouteProviderLazyBuilder {

  public function __construct(RouteProviderInterface $route_provider, RouteBuilderInterface $route_builder) {
    parent::__construct($route_provider, $route_builder);

    // The installer cannot rely on the routing table being populated; the
    // first lookup must trigger a rebuild.
    $this->markStale();
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteByName($name) {
    if ($name === '<none>' || $name === '<front>') {
      // During the installer
      // \Drupal\Core\Theme\ThemePreprocess::preprocessPage() uses the routing
      // system to determine the front page. At this point building the router
      // for this is unnecessary work.
      return new Route('/');
    }
    return parent::getRouteByName($name);
  }

}
