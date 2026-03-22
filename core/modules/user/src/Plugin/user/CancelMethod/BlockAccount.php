<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\user\CancelMethod;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\AccountCancelMethodPluginBase;
use Drupal\user\Attribute\AccountCancelMethod;
use Drupal\user\UserInterface;

/**
 * Cancels the user by blocking them.
 */
#[AccountCancelMethod(
  id: 'user_cancel_block',
  label: new TranslatableMarkup('Disable the account and keep its content.'),
  description: new TranslatableMarkup('Your account will be blocked and you will no longer be able to log in. All of your content will remain attributed to your username.'),
)]
class BlockAccount extends AccountCancelMethodPluginBase {

  /**
   * {@inheritdoc}
   */
  public function cancel(UserInterface $account, array $context = []): void {
    // Send account blocked notification if it was requested.
    if (!empty($context['user_cancel_notify'])) {
      _user_mail_notify('status_blocked', $account);
    }
    $account->block()->save();
    $this->messenger()->addStatus($this->t('Account %name has been disabled.', [
      '%name' => $account->getDisplayName(),
    ]));
    $this->logger->notice('Blocked user: %name %email.', [
      '%name' => $account->getAccountName(),
      '%email' => '<' . $account->getEmail() . '>',
    ]);
  }

}
