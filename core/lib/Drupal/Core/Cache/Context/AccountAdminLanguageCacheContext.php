<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;

/**
 * Defines an AdminLanguageCacheContext service, for "admin language" caching.
 *
 * Cache context ID: 'user.admin_language'.
 */
class AccountAdminLanguageCacheContext implements CacheContextInterface {

  /**
   * Constructs a new AccountAdminLanguageCacheContext service.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
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
    // Resolve through language negotiation so this context stays consistent
    // with the language the administration pages render in. The langcode is
    // empty when no enabled negotiation method provides an administration
    // language for the current user, so the context does not vary by an
    // unused preference.
    if (!$this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      return '';
    }
    return $this->languageManager->getAdminLangcode();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    // The resolved value depends on which negotiation methods are enabled,
    // which is stored in the language.types configuration.
    return (new CacheableMetadata())->addCacheTags(['config:language.types']);
  }

}
