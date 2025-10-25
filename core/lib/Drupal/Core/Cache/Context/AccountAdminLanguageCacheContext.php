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
   * Constructs a new AccountAdminLanguageCacheContext service.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(protected AccountProxyInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string {
    return t("Account's administration language");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    return $this->currentUser->getPreferredAdminLangcode(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return new CacheableMetadata();
  }

}
