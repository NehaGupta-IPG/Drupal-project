<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Defines an AdminLanguageCacheContext service, for "admin language" caching.
 *
 * Cache context ID: 'user.admin_language'.
 */
class AccountAdminLanguageCacheContext implements CacheContextInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a new AccountAdminLanguageCacheContext service.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Account's administration language");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->currentUser->getPreferredAdminLangcode(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
