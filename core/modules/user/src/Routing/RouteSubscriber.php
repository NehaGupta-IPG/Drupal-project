<?php

namespace Drupal\user\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Site\Settings;
use Drupal\gin\Helper;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Helper function for check if modern login is enabled.
   */
  public static function isUserLoginAdminThemeEnabled(): bool {
    // @todo After #Gin-merge-into-Core set class to "\Drupal\admin\Helper".
    return class_exists('\Drupal\gin\Helper') && Settings::get('user_login_admin_theme', TRUE);
  }

  /**
   * Helper function for check if modern login is enabled.
   */
  public static function useAdminThemeForLogin(): bool {
    return self::isUserLoginAdminThemeEnabled() && array_key_exists(\Drupal::routeMatch()->getRouteName(), Helper::getLoginRouteDefinitions());
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if (!self::isUserLoginAdminThemeEnabled()) {
      return;
    }
    foreach (Helper::getLoginRouteDefinitions() as $id => $loginRouteDefinition) {
      if ($route = $collection->get($id)) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
