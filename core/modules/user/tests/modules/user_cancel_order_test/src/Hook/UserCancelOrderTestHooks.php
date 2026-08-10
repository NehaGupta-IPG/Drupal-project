<?php

declare(strict_types=1);

namespace Drupal\user_cancel_order_test\Hook;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Hook implementations for user cancellation order tests.
 */
final class UserCancelOrderTestHooks {

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel(array $edit, UserInterface $account, string $method): void {
    $state = \Drupal::state();
    $node_id = $state->get('user_cancel_order_test.node_id');
    if (!$node_id) {
      return;
    }

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    if (!$node instanceof EntityOwnerInterface) {
      return;
    }
    $state->set(
      'user_cancel_order_test.synchronous_owner_id',
      (int) $node->getOwnerId(),
    );

    $batch = (new BatchBuilder())
      ->addOperation([self::class, 'recordOwner'], [$node_id]);
    batch_set($batch->toArray());
  }

  /**
   * Records the entity owner from a legacy hook batch operation.
   */
  public static function recordOwner(int $node_id, array &$context): void {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    if ($node instanceof EntityOwnerInterface) {
      \Drupal::state()->set(
        'user_cancel_order_test.batch_owner_id',
        (int) $node->getOwnerId(),
      );
    }
  }

}
