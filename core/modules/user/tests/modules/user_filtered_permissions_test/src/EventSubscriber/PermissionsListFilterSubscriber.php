<?php

declare(strict_types=1);

namespace Drupal\user_filtered_permissions_test\EventSubscriber;

use Drupal\user\Event\PermissionsListFilterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A subscriber for testing PermissionsListFilterEvent.
 */
class PermissionsListFilterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [PermissionsListFilterEvent::class => 'filterPermissions'];
  }

  /**
   * Takes a permissions array and filters it.
   *
   * @param \Drupal\user\Event\PermissionsListFilterEvent $event
   *   The permissions filter list event.
   */
  public function filterPermissions(PermissionsListFilterEvent $event):void {
    $test_case = \Drupal::state()->get('user_filtered_permissions_test.test_case');
    switch ($test_case) {
      case 'no node permissions':
        $event->filter(fn(array $permission_data, string $permission_name) => $permission_data['provider'] !== 'node');
        break;

      case 'no view own published content':
        $event->filter(fn(array $permission_data, string $permission_name) => $permission_name !== 'view own unpublished content');
        break;

      default:
        // Filter every permission that is not a, b, or c.
        $event->filter(fn(array $permission_data, string $permission_name) => in_array($permission_name, ['a', 'b', 'c']));
        break;
    }
  }

}
