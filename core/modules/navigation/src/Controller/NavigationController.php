<?php

declare(strict_types=1);

namespace Drupal\navigation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\PathChangedHelper;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Navigation routes.
 */
final class NavigationController extends ControllerBase {

  /**
   * Redirects from the old navigation block layout path to the new one.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A route match object, used for the route name and the parameters.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the new path.
   *
   * @deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. The path
   *   /admin/config/user-interface/navigation-block has moved to
   *   /admin/config/user-interface/navigation/layout.
   *
   * @see https://www.drupal.org/node/3578445
   */
  public function layoutBc(RouteMatchInterface $route_match, Request $request): RedirectResponse {
    @trigger_error('The path /admin/config/user-interface/navigation-block is deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use /admin/config/user-interface/navigation/layout instead. See https://www.drupal.org/node/3578445', E_USER_DEPRECATED);
    $helper = new PathChangedHelper($route_match, $request);
    $change_record = 'https://www.drupal.org/node/3578445';
    $params = [
      '%old_path' => $helper->oldPath(),
      '%new_path' => $helper->newPath(),
      '%change_record' => $change_record,
    ];
    $warning_message = $this->t('You have been redirected from %old_path. Update links, shortcuts, and bookmarks to use %new_path.', $params);
    $this->messenger()->addWarning($warning_message);
    $this->getLogger('navigation')
      ->warning('A user was redirected from %old_path. This redirect will be removed in a future version of Drupal. Update links, shortcuts, and bookmarks to use %new_path. See %change_record for more information.', $params);
    return $helper->redirect();
  }

}
