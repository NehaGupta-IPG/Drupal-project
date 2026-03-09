<?php

declare(strict_types=1);

namespace Drupal\menu_test\EventSubscriber;

use Drupal\Core\Menu\MenuLinkTreeManipulatorsAlterEvent;
use Drupal\menu_test\MenuLinkManipulators;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Menu tree subscriber for menu tree manipulator events.
 */
class MenuLinkTreeEventSubscriber implements EventSubscriberInterface {

  /**
   * Add our test menu link manipulators.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeManipulatorsAlterEvent $event
   *   The event.
   */
  public static function alterMenuLinkManipulators(MenuLinkTreeManipulatorsAlterEvent $event): void {
    $manipulators = $event->getManipulators();
    // Append the test menu link manipulator.
    $manipulators[] = ['callable' => MenuLinkManipulators::class . ':testManipulator'];
    $event->setManipulators($manipulators);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MenuLinkTreeManipulatorsAlterEvent::class => ['alterMenuLinkManipulators'],
    ];
  }

}
