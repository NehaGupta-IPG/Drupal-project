<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for account cancellation methods plugins.
 */
interface AccountCancelMethodInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Checks whether the passed account has access to the cancellation method.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   Whether the passed account has access to the cancellation method.
   */
  public function access(AccountInterface $account): bool;

  /**
   * Cancels the account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param array $context
   *   (optional) Associative array with context value. For instance, when used
   *   in the form API, the submitted form values can be passed in this array.
   */
  public function cancel(UserInterface $account, array $context = []): void;

}
