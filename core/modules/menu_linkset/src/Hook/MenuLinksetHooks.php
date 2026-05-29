<?php

declare(strict_types=1);

namespace Drupal\menu_linkset\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for menu_linkset.
 */
class MenuLinksetHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.menu_linkset') {
      return NULL;
    }
    $output = '<h2>' . $this->t('About') . '</h2>';
    $output .= '<p>' . $this->t('The Menu Linkset module exposes site menus over an HTTP endpoint as <code>application/linkset+json</code> responses, for use by decoupled Drupal front ends.') . '</p>';
    $output .= '<p>' . $this->t('Once installed, each menu is available at <code>/system/menu/{menu}/linkset</code>. The endpoint accepts any of the authentication providers enabled on the site.') . '</p>';
    $output .= '<p>' . $this->t('For more information, see the <a href=":href">online documentation for the Menu Linkset module</a>.', [
      ':href' => 'https://www.drupal.org/docs/develop/decoupled-drupal/decoupled-menus',
    ]) . '</p>';
    return $output;
  }

}
