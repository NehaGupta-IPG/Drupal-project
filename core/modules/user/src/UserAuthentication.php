<?php

namespace Drupal\user;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;

/**
 * Validates user authentication credentials.
 */
class UserAuthentication implements UserAuthInterface, UserAuthenticationInterface {

  /**
   * Constructs a UserAuth object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Password\PasswordInterface $passwordChecker
   *   The password service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PasswordInterface $passwordChecker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function authenticate($username, #[\SensitiveParameter] $password) {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:10.3.0 and will be removed from drupal:12.0.0. Implement \Drupal\user\UserAuthenticationInterface instead. See https://www.drupal.org/node/3411040');
    $uid = FALSE;

    if (!empty($username) && strlen($password) > 0) {
      $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]);

      if ($account = reset($account_search)) {
        if ($this->authenticateAccount($account, $password)) {
          $uid = $account->id();
        }
      }
    }
    return $uid;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupAccount($identifier): UserInterface|false {
    if (!empty($identifier)) {
      $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $identifier]);

      if ($account = reset($account_search)) {
        return $account;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticateAccount(UserInterface $account, #[\SensitiveParameter] string $password): bool {
    if ($this->passwordChecker->check($password, $account->getPassword())) {
      // Update user to new password scheme if needed.
      if ($this->passwordChecker->needsRehash($account->getPassword())) {
        $account->setPassword($password);
        $account->save();
      }
      return TRUE;
    }
    return FALSE;
  }

}
