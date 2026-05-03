<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;

/**
 * Help hook implementations for content_translation.
 */
class ContentTranslationHelpHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.content_translation':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Content Translation module allows you to translate content, comments, content blocks, taxonomy terms, users and other <a href=":field_help" title="Field module help, with background on content entities">content entities</a>. Together with the modules <a href=":language">Language</a>, <a href=":config-trans">Configuration Translation</a>, and <a href=":locale">Interface Translation</a>, it allows you to build multilingual websites. For more information, see the <a href=":translation-entity">online documentation for the Content Translation module</a>.', [
          ':locale' => $this->moduleHandler->moduleExists('locale') ? Url::fromRoute('help.page', [
            'name' => 'locale',
          ])->toString() : '#',
          ':config-trans' => $this->moduleHandler->moduleExists('config_translation') ? Url::fromRoute('help.page', [
            'name' => 'config_translation',
          ])->toString() : '#',
          ':language' => Url::fromRoute('help.page', [
            'name' => 'language',
          ])->toString(),
          ':translation-entity' => 'https://www.drupal.org/docs/8/core/modules/content-translation',
          ':field_help' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Enabling translation') . '</dt>';
        $output .= '<dd>' . $this->t('In order to translate content, the website must have at least two <a href=":url">languages</a>. When that is the case, you can enable translation for the desired content entities on the <a href=":translation-entity">Content language</a> page. When enabling translation you can choose the default language for content and decide whether to show the language selection field on the content editing forms.', [
          ':url' => Url::fromRoute('entity.configurable_language.collection')->toString(),
          ':translation-entity' => Url::fromRoute('language.content_settings_page')->toString(),
          ':language-help' => Url::fromRoute('help.page', [
            'name' => 'language',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Enabling field translation') . '</dt>';
        $output .= '<dd>' . $this->t('You can define which fields of a content entity can be translated. For example, you might want to translate the title and body field while leaving the image field untranslated. If you exclude a field from being translated, it will still show up in the content editing form, but any changes made to that field will be applied to <em>all</em> translations of that content.') . '</dd>';
        $output .= '<dt>' . $this->t('Translating content') . '</dt>';
        $output .= '<dd>' . $this->t('If translation is enabled you can translate a content entity via the Translate tab (or Translate link). The Translations page of a content entity gives an overview of the translation status for the current content and lets you add, edit, and delete its translations. This process is similar for every translatable content entity on your site.') . '</dd>';
        $output .= '<dt>' . $this->t('Changing the source language for a translation') . '</dt>';
        $output .= '<dd>' . $this->t('When you add a new translation, the original text you are translating is displayed in the edit form as the <em>source</em>. If at least one translation of the original content already exists when you add a new translation, you can choose either the original content (default) or one of the other translations as the source, using the select list in the Source language section. After saving the translation, the chosen source language is then listed on the Translate tab of the content.') . '</dd>';
        $output .= '<dt>' . $this->t('Setting status of translations') . '</dt>';
        $output .= '<dd>' . $this->t('If you edit a translation in one language you may want to set the status of the other translations as <em>out-of-date</em>. You can set this status by selecting the <em>Flag other translations as outdated</em> checkbox in the Translation section of the content editing form. The status will be visible on the Translations page.') . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'language.content_settings_page':
        $output = '';
        if (!$this->languageManager->isMultilingual()) {
          $output .= '<p>' . $this->t('Before you can translate content, there must be at least two languages added on the <a href=":url">languages administration</a> page.', [
            ':url' => Url::fromRoute('entity.configurable_language.collection')->toString(),
          ]) . '</p>';
        }
        return $output;
    }
    return NULL;
  }

}
