<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Menu\fixtures\invalid\Controller;

use Drupal\Core\Menu\Attribute\MenuLink;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A controller fixture with an ambiguous #[MenuLink].
 *
 * The method declares more than one route, but the menu link does not name the
 * route it points to, so discovery cannot resolve it.
 */
class MenuLinkAmbiguousController {

  /**
   * A method with multiple routes and an under-specified menu link.
   *
   * @return array
   *   A render array.
   */
  #[Route(path: '/menu-link-test/x', name: 'menu_link_test.x')]
  #[Route(path: '/menu-link-test/y', name: 'menu_link_test.y')]
  #[MenuLink(
    title: new TranslatableMarkup('Ambiguous'),
  )]
  public function ambiguous(): array {
    return [];
  }

}
