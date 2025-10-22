<?php

declare(strict_types=1);

namespace Drupal\Core\Menu;

use Drupal\Component\EventDispatcher\Event;

/**
 * Represents menu link tree manipulator information as event.
 */
class MenuLinkTreeManipulatorsAlterEvent extends Event {

  public function __construct(
    protected array $tree,
    protected array $manipulators,
    protected MenuLinkTreeInterface $menuLinkTree,
  ) {
  }

  /**
   * The MenuLinkElement tree.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The menu tree.
   */
  public function getTree(): array {
    return $this->tree;
  }

  /**
   * Get the manipulators for the tree.
   *
   * @return array
   *   The menu tree manipulators.
   */
  public function getManipulators(): array {
    return $this->manipulators;
  }

  /**
   * Set the manipulators for the tree.
   *
   * @param array $manipulators
   *   The menu tree manipulators.
   */
  public function setManipulators(array $manipulators): void {
    $this->manipulators = $manipulators;
  }

  /**
   * The MenuLinkTree.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeInterface
   *   The menu link tree.
   */
  public function getMenuLinkTree(): MenuLinkTreeInterface {
    return $this->menuLinkTree;
  }

}
