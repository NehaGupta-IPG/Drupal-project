<?php

namespace Drupal\Core\Session;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired when an account is set for the current session.
 */
final class AccountSetEvent extends Event {

  /**
   * The set account.
   *
   * @var AccountInterface
   */
  protected $account;

  /**
   * AccountSetEvent constructor.
   *
   * @param AccountInterface $account
   *   The set account.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
  }

  /**
   * Gets the account.
   *
   * @return AccountInterface
   *   The account.
   */
  public function getAccount() {
    return $this->account;
  }

}
