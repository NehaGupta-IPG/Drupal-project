<?php

declare(strict_types=1);

namespace Drupal\user\Entity\Handler;

use Drupal\user\UserInterface;

/**
 * Preserves potentially shared content when an account is deleted.
 *
 * Shared usage cannot be determined generically, so deletion is mapped to
 * reassignment rather than risking data loss.
 *
 * @internal
 */
final class ReassignOnDeleteCancellationHandler extends DefaultCancellationHandler {

  /**
   * {@inheritdoc}
   */
  public function cancelAccount(UserInterface $account, string $method): void {
    parent::cancelAccount(
      $account,
      $method === self::METHOD_DELETE ? self::METHOD_REASSIGN : $method,
    );
  }

}
