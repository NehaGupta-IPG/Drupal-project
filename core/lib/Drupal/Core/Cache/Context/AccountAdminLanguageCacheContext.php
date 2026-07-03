<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUserAdmin;

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
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected LanguageManagerInterface $languageManager,
  ) {}

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
    // The admin language preference only affects rendering when the "Account
    // administration pages" negotiation method is enabled. Otherwise the
    // stored preference is unused, and this context must not vary by it.
    if (!$this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      return '';
    }
    $negotiator = $this->languageManager->getNegotiator();
    if (!$negotiator || !$negotiator->isNegotiationMethodEnabled(LanguageNegotiationUserAdmin::METHOD_ID)) {
      return '';
    }

    return $this->currentUser->getPreferredAdminLangcode(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return new CacheableMetadata();
  }

}
