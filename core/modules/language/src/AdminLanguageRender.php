<?php

namespace Drupal\language;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationManager;

/**
 * The admin language render class.
 */
class AdminLanguageRender implements TrustedCallbackInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs an AdminLanguageRender object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translationManager
   *   The translation manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(LanguageManagerInterface $languageManager, TranslationManager $translationManager, AccountInterface $currentUser) {
    $this->languageManager = $languageManager;
    $this->translationManager = $translationManager;
    $this->currentUser = $currentUser;
  }

  /**
   * Adds the render callbacks to a render element.
   *
   * @param array $type
   *   A render element that will be altered to switch to the admin language
   *   while rendering.
   *
   * @return array
   *   A renderable array.
   */
  public static function applyTo(array $type): array {
    if (!isset($type['#pre_render'])) {
      $type['#pre_render'] = [];
    }
    if (!isset($type['#post_render'])) {
      $type['#post_render'] = [];
    }
    // Switch to the admin language as early as possible and then switch back as
    // late as possible.
    array_unshift($type['#pre_render'], 'language.admin_language_render:switchToUserAdminLanguage');
    $type['#post_render'][] = 'language.admin_language_render:restoreLanguage';
    return $type;
  }

  /**
   * Sets admin language.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   A renderable array.
   */
  #[TrustedCallback]
  public function switchToUserAdminLanguage(array $element) {
    $userAdminLangcode = $this->currentUser->getPreferredAdminLangcode(FALSE);

    if ($userAdminLangcode && ($this->currentUser->hasPermission('access administration pages') || $this->currentUser->hasPermission('view the administration theme'))) {
      $element['#original_langcode'] = $this->languageManager->getCurrentLanguage()->getId();
      $this->languageManager->setCurrentLanguage($this->languageManager->getLanguage($userAdminLangcode));
      $this->translationManager->setDefaultLangcode($userAdminLangcode);
      $this->languageManager->setConfigOverrideLanguage($this->languageManager->getLanguage($userAdminLangcode));
    }

    // Add the correct cache contexts in.
    $metadata = CacheableMetadata::createFromRenderArray($element);
    $metadata->addCacheContexts(['user.admin_language', 'user.permissions']);
    $metadata->applyTo($element);

    return $element;
  }

  /**
   * Restore original language.
   *
   * @param \Drupal\Core\Render\Markup $content
   *   Rendered markup.
   * @param array $element
   *   A renderable array.
   *
   * @return \Drupal\Core\Render\Markup
   *   Rendered markup.
   */
  #[TrustedCallback]
  public function restoreLanguage($content, $element) {
    if (isset($element['#original_langcode'])) {
      $langcode = $element['#original_langcode'];
      $language = $this->languageManager->getLanguage($langcode);
      $this->languageManager->setCurrentLanguage($language);
      $this->translationManager->setDefaultLangcode($langcode);
      $this->languageManager->setConfigOverrideLanguage($language);
    }

    return $content;
  }

}
