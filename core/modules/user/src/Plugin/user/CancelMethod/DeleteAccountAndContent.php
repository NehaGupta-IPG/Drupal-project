<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\user\CancelMethod;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Attribute\AccountCancelMethod;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cancels the user by deleting them and their content.
 */
#[AccountCancelMethod(
  id: 'user_cancel_delete',
  label: new TranslatableMarkup('Delete the account and its content. This action cannot be undone.'),
  description: new TranslatableMarkup('Your account will be removed and all account information deleted. All of your content will also be deleted.'),
)]
class DeleteAccountAndContent extends DeleteAccountAndReassign {

  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    #[Autowire(service: 'logger.channel.user')]
    LoggerChannelInterface $logger,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    return $this->currentUser->hasPermission('administer users');
  }

}
