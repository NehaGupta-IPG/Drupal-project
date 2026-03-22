<?php

declare(strict_types=1);

namespace Drupal\user_cancel_test\Plugin\user\CancelMethod;

use Drupal\user\AccountCancelMethodPluginBase;
use Drupal\user\Attribute\AccountCancelMethod;
use Drupal\user\UserInterface;

/**
 * Testing account cancellation method.
 */
#[AccountCancelMethod(
  id: 'user_cancel_test',
  label: 'User cancel test method',
)]
class UserCancelTest extends AccountCancelMethodPluginBase {

  /**
   * {@inheritdoc}
   */
  public function cancel(UserInterface $account, array $context = []): void {
    $this->messenger()->addStatus('Custom user cancel method executed.');
  }

}
