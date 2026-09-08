<?php

declare(strict_types=1);

namespace Drupal\content_moderation\Entity\Handler;

use Drupal\user\Entity\Handler\DefaultCancellationHandler;
use Drupal\user\UserInterface;

/**
 * Handles account cancellation for internal moderation state entities.
 */
final class CancellationHandler extends DefaultCancellationHandler {

  /**
   * {@inheritdoc}
   */
  public function cancelAccount(UserInterface $account, string $method): void {
    // Moderation states are metadata for another entity and must not be
    // deleted independently when their owner account is deleted.
    if ($method === self::METHOD_REASSIGN || $method === self::METHOD_DELETE) {
      $this->processor->reassign($this->entityType, (int) $account->id());
    }
  }

}
