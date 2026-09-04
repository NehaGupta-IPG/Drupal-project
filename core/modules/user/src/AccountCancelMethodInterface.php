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
   * @param array $options
   *   (optional) An associative array of options to control the behavior of
   *   the cancellation method. E.g., set `user_cancel_notify` to `TRUE` if
   *   a user should be notified when the account was blocked.
   */
  public function cancel(UserInterface $account, array $options = []): void;

  /**
   * Prepare all the content owned by an account about to be canceled.
   *
   * Identifies all the content owned by an account and add appropriate
   * operations to delete or disassociate it from the account using the batch
   * API.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account to cancel.
   * @param string $method
   *   The account cancellation method to use.
   * @param array $context
   *   (optional) Associative array with context value. For instance, when used
   *   in the form API, the submitted form values can be passed in this array.
   */
  public function prepareOwnedContent(UserInterface $account, string $method, array $context = []): void;

}
