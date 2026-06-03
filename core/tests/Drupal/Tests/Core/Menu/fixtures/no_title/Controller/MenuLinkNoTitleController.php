<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Menu\fixtures\no_title\Controller;

use Drupal\Core\Menu\Attribute\MenuLink;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A controller fixture whose menu link cannot resolve a title.
 *
 * Neither the menu link nor its route declares a title.
 */
class MenuLinkNoTitleController {

  /**
   * A method whose route has no _title default and link has no title.
   *
   * @return array
   *   A render array.
   */
  #[Route(path: '/menu-link-test/no-title', name: 'menu_link_test.no_title')]
  #[MenuLink(parent: 'system.admin')]
  public function noTitle(): array {
    return [];
  }

}
