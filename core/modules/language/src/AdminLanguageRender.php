<?php

namespace Drupal\language;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    #[Autowire(service: 'string_translation')] protected TranslationManager $translationManager,
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
    $originalLangcode = $this->switchToAdminLanguage();
    if ($originalLangcode !== NULL) {
      $element['#original_langcode'] = $originalLangcode;
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
      $this->restoreAdminLanguage($element['#original_langcode']);
    }

    return $content;
  }

  /**
   * Switches to the current user's preferred admin language, if applicable.
   *
   * No switch happens if the admin language negotiation method is disabled,
   * the user has no admin language preference, or the user does not have
   * access to the administration pages.
   *
   * @return string|null
   *   The langcode that was active before switching, or NULL if no switch
   *   took place.
   */
  public function switchToAdminLanguage(): ?string {
    if (!$this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      return NULL;
    }
    $userAdminLangcode = $this->languageManager->getAdminLangcode();
    if ($userAdminLangcode === '') {
      return NULL;
    }

    $originalLangcode = $this->languageManager->getCurrentLanguage()->getId();
    $adminLanguage = $this->languageManager->getLanguage($userAdminLangcode);
    $this->languageManager->setCurrentLanguage($adminLanguage);
    $this->translationManager->setDefaultLangcode($userAdminLangcode);
    $this->languageManager->setConfigOverrideLanguage($adminLanguage);
    return $originalLangcode;
  }

  /**
   * Restores the language that was active before switchToAdminLanguage().
   *
   * @param string $langcode
   *   The langcode returned by switchToAdminLanguage().
   */
  public function restoreAdminLanguage(string $langcode): void {
    $language = $this->languageManager->getLanguage($langcode);
    $this->languageManager->setCurrentLanguage($language);
    $this->translationManager->setDefaultLangcode($langcode);
    $this->languageManager->setConfigOverrideLanguage($language);
  }

}
