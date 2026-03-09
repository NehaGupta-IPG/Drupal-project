<?php

declare(strict_types=1);

namespace Drupal\menu_test;

/**
 * A menu manipulator.
 */
final class MenuLinkManipulators {

  /**
   * Add the class menu-test-link to certain links.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu tree.
   *
   * @return array
   *   The menu manipulators.
   */
  public function testManipulator(array $tree): array {
    foreach ($tree as $key => $element) {
      $link = $tree[$key]->link;
      $url = $link->getUrlObject();
      if ($url->isRouted()) {
        $manipulators = ['menu_test.manipulator2', 'menu_test.manipulator3'];
        if (in_array($url->getRouteName(), $manipulators)) {
          $tree[$key]->options['attributes']['class'][] = 'menu-test-link';
        }
      }
    }
    return $tree;
  }

}
