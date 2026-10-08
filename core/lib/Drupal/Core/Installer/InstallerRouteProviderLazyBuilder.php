<?php

namespace Drupal\Core\Installer;

use Drupal\Core\Routing\RouteProvider;
use Symfony\Component\Routing\Route;

/**
 * A Route Provider front-end for use during the installer.
 */
class InstallerRouteProviderLazyBuilder extends RouteProvider {

  /**
   * {@inheritdoc}
   *
   * The installer cannot rely on the routing table being populated, so the
   * first lookup must trigger a rebuild.
   */
  protected bool $rebuilt = FALSE;

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
