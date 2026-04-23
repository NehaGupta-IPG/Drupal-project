<?php

namespace Drupal\language;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationManager;

/**
 * Provides a render element in the user's preferred admin language.
 */
class AdminLanguageRender {

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
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected TranslationManager $translationManager,
    protected AccountInterface $currentUser,
  ) {}

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
    array_unshift($type['#pre_render'], 'Drupal\language\AdminLanguageRender:switchToUserAdminLanguage');
    $type['#post_render'][] = 'Drupal\language\AdminLanguageRender:restoreLanguage';
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
  public function switchToUserAdminLanguage(array $element): array {
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
  public function restoreLanguage(Markup $content, array $element): Markup {
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
