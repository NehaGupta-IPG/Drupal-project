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
    $account = $this->getAccount($param);

    if (!$account) {
      return $callback();
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
   * Loads the account specified in the environment parameter.
   *
   * @param \Drupal\Core\Environment\EnvironmentParameter $param
   *   The environment parameter.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   Returns the specified account or NULL if none was found.
   *
   * @throws \InvalidArgumentException
   *   If the account cannot be found and the parameter is not optional.
   */
  protected function getAccount(EnvironmentParameter $param): ?AccountInterface {
    $uid = $param->value;
    if (!$uid) {
      return new AnonymousUserSession();
    }

    assert(is_string($uid) || is_int($uid));
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($account) {
      assert($account instanceof AccountInterface);
      return $account;
    }

    if ($param->optional) {
      return NULL;
    }

    throw new \InvalidArgumentException(sprintf('Failed to load the specified user with uid %s', (string) $uid));
  }

}
