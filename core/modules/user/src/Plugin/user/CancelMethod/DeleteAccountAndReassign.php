<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\user\CancelMethod;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\AccountCancelMethodPluginBase;
use Drupal\user\Attribute\AccountCancelMethod;
use Drupal\user\NotificationHandler;
use Drupal\user\UserInterface;

/**
 * Cancels the user by deleting them and reassigning their content to anonymous.
 */
#[AccountCancelMethod(
  id: 'user_cancel_reassign',
  label: new TranslatableMarkup('Delete the account and make its content belong to the anonymous user. This action cannot be undone.'),
  description: new TranslatableMarkup('Your account will be removed and all account information deleted. All of your content will be assigned to the anonymous user.'),
)]
class DeleteAccountAndReassign extends AccountCancelMethodPluginBase {

  /**
   * {@inheritdoc}
   */
  public function cancel(UserInterface $account, array $context = []): void {
    // Send account canceled notification if it was requested.
    if (!empty($context['user_cancel_notify'])) {
      \Drupal::service(NotificationHandler::class)->sendStatusCanceled($account);
    }
    $account->delete();
    $this->messenger()->addStatus($this->t('Account %name has been deleted.', [
      '%name' => $account->getDisplayName(),
    ]));
    $this->logger->notice('Deleted user: %name %email.', [
      '%name' => $account->getAccountName(),
      '%email' => '<' . $account->getEmail() . '>',
    ]);
  }

}
