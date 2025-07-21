<?php

declare(strict_types=1);

namespace Drupal\Core\Menu;

/**
 * Defines events for the menu link tree system.
 *
 * @see \Drupal\Core\Menu\MenuLinkTreeManipulatorsAlterEvent
 */
final class MenuLinkTreeEvents {

  /**
   * Name of the event fired during menu link tree manipulator collection.
   *
   * This event allows modules to modify menu tree manipulators. The event
   * listener method receives a
   * \Drupal\Core\Menu\MenuLinkTreeManipulatorsAlterEvent instance.
   *
   * @Event
   *
   * @see \Drupal\Core\Menu\MenuLinkTreeManipulatorsAlterEvent
   */
  const ALTER_MANIPULATORS = 'menu.link_tree.alter_manipulators';

}
