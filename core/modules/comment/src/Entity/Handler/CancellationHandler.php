<?php

declare(strict_types=1);

namespace Drupal\comment\Entity\Handler;

use Drupal\user\Entity\Handler\DefaultCancellationHandler;
use Drupal\user\UserInterface;

/**
 * Handles comment authorship during user cancellation.
 */
final class CancellationHandler extends DefaultCancellationHandler {

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    // Comments must be processed before entities that may host them.
    return 10;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelAccount(UserInterface $account, string $method): void {
    if ($method !== self::METHOD_REASSIGN) {
      parent::cancelAccount($account, $method);
      return;
    }
    // Reassign ownership and stored author name in a single entity pass.
    $this->processor->reassign(
      $this->entityType,
      (int) $account->id(),
      ['name' => \Drupal::config('user.settings')->get('anonymous')],
    );
  }

}
