<?php

declare(strict_types=1);

namespace Drupal\default_admin_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for default_admin_test.
 */
class DefaultAdminTestHooks {

  /**
   * Implements hook_default_admin_content_form_routes().
   *
   * The route does not have to exist, because the theme only compares it to
   * the name of the current route.
   *
   * @see \Drupal\default_admin\Helper::isContentForm()
   */
  #[Hook('default_admin_content_form_routes')]
  public function contentFormRoutes(): array {
    return ['default_admin_test.content_form'];
  }

  /**
   * Implements hook_default_admin_content_form_routes_alter().
   *
   * Removes one of the routes the theme registers itself, to prove that the
   * alter hook can opt a route out of the content form layout.
   */
  #[Hook('default_admin_content_form_routes_alter')]
  public function contentFormRoutesAlter(array &$routes): void {
    $routes = array_diff($routes, ['menu_ui.link_edit']);
  }

  /**
   * Implements hook_default_admin_content_form_ignore_form_ids().
   */
  #[Hook('default_admin_content_form_ignore_form_ids')]
  public function contentFormIgnoreFormIds(): array {
    return ['default_admin_test_ignored_'];
  }

}
