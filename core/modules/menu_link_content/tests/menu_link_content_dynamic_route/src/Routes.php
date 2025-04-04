<?php

declare(strict_types=1);

namespace Drupal\menu_link_content_dynamic_route;

/**
 * Provides dynamic routes for test purposes.
 */
class Routes {

  /**
   * Gets the menu link dynamic routes.
   */
  public function dynamic() {
    return \Drupal::state()->get('menu_link_content_dynamic_route.routes', []);
  }

}
