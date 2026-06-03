<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Menu\fixtures\valid\Controller;

use Drupal\Core\Menu\Attribute\MenuLink;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A controller fixture declaring menu links with the #[MenuLink] attribute.
 */
class MenuLinkTestController {

  /**
   * A method with a single route; the link derives its ID from that route.
   *
   * @return array
   *   A render array.
   */
  #[Route(path: '/menu-link-test/single', name: 'menu_link_test.single', defaults: ['_title' => new TranslatableMarkup('Single')])]
  #[MenuLink(
    parent: 'system.admin',
    weight: 5,
  )]
  public function single(): array {
    return [];
  }

  /**
   * A method with a single route, but an explicit menu link ID.
   *
   * @return array
   *   A render array.
   */
  #[Route(path: '/menu-link-test/explicit', name: 'menu_link_test.route')]
  #[MenuLink(
    id: 'menu_link_test.explicit_id',
    title: new TranslatableMarkup('Explicit'),
  )]
  public function explicit(): array {
    return [];
  }

  /**
   * A method with several routes; the link names the route it points to.
   *
   * @return array
   *   A render array.
   */
  #[Route(path: '/menu-link-test/a', name: 'menu_link_test.a')]
  #[Route(path: '/menu-link-test/b', name: 'menu_link_test.b')]
  #[MenuLink(
    title: new TranslatableMarkup('Multi'),
    route: 'menu_link_test.b',
  )]
  public function multi(): array {
    return [];
  }

}
