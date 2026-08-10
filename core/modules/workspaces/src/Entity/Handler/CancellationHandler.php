<?php

declare(strict_types=1);

namespace Drupal\workspaces\Entity\Handler;

use Drupal\user\Entity\Handler\DefaultCancellationHandler;
use Drupal\user\UserInterface;

/**
 * Handles workspace ownership during user cancellation.
 */
final class CancellationHandler extends DefaultCancellationHandler {

  /**
   * {@inheritdoc}
   */
  public function cancelAccount(UserInterface $account, string $method): void {
    if (in_array($method, [self::METHOD_REASSIGN, self::METHOD_DELETE], TRUE)) {
      // Workspaces contain revisions that may belong to other accounts, so
      // preserve the workspace hierarchy when its owner account is deleted.
      $this->processor->reassign(
        $this->entityType,
        (int) $account->id(),
      );
    }
  }

}
