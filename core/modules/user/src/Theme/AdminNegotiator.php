<?php

namespace Drupal\user\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Sets the active theme on admin pages.
 */
class AdminNegotiator implements ThemeNegotiatorInterface {
  use DeprecatedServicePropertyTrait;

  /**
   * The service properties that should raise a deprecation error.
   */
  private array $deprecatedProperties = ['entityTypeManager' => 'entity_type.manager'];

  /**
   * The route admin context to determine whether a route is an admin one.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * Creates a new AdminNegotiator instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Routing\AdminContext|EntityTypeManagerInterface $admin_context
   *   The route admin context to determine whether the route is an admin one.
   */
  public function __construct(
    protected AccountInterface $user,
    protected ConfigFactoryInterface $configFactory,
    AdminContext|EntityTypeManagerInterface $admin_context,
  ) {
    if ($admin_context instanceof EntityTypeManagerInterface) {
      $deprecated_service_name = EntityTypeManagerInterface::class;
      @trigger_error("Passing the $deprecated_service_name (entity_type.manager service) to AdminNegotiator is deprecated in drupal:11.2.0 and will be removed in drupal:12.0.0. There is no replacement for this service, as it is not used. See https://www.drupal.org/project/drupal/issues/3501727", E_USER_DEPRECATED);
      $this->adminContext = \Drupal::service('router.admin_context');
    }
    else {
      $this->adminContext = $admin_context;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $is_admin_route = $this->adminContext->isAdminRoute($route_match->getRouteObject());
    return $is_admin_route && $this->user->hasPermission('view the administration theme');
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->configFactory->get('system.theme')->get('admin') ?: NULL;
  }

}
