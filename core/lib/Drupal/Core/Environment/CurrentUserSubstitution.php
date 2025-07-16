<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Switches the current user.
 */
class CurrentUserSubstitution implements SubstitutionInterface {

  use SubstitutionApplyTrait;

  /**
   * Constructs a new current user substitution.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed {
    $uid = $param->value;
    if ($uid) {
      assert(is_string($uid) || is_int($uid));
      $account = $this->loadUser($uid);
    }
    else {
      $account = new AnonymousUserSession();
    }

    $this->accountSwitcher->switchTo($account);
    try {
      return $callback();
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Load a user account from storage.
   *
   * @param string|int $uid
   *   The user id.
   */
  protected function loadUser(string|int $uid): AccountInterface {
    $account = $this->entityTypeManager->getStorage('user')->load($uid);

    if (!$account) {
      throw new \InvalidArgumentException(sprintf('Failed to load the specified user with uid %s', (string) $uid));
    }

    assert($account instanceof AccountInterface);
    return $account;
  }

}
