<?php

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'language' formatter.
 */
#[FieldFormatter(
  id: 'language',
  label: new TranslatableMarkup('Language'),
  field_types: [
    'language',
  ],
)]
class LanguageFormatter extends StringFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['native_language'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['native_language'] = [
      '#title' => $this->t('Display in native language'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('native_language'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('native_language')) {
      $summary[] = $this->t('Displayed in native language');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    // The 'languages' cache context is not necessary because the language is
    // either displayed in its configured form (loaded directly from config
    // storage by LanguageManager::getLanguages()) or in its native language
    // name. That only depends on formatter settings and no language condition.
    $languages = $this->getSetting('native_language') ? $this->languageManager->getNativeLanguages(LanguageInterface::STATE_ALL) : $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    // \Drupal\Core\Language\LanguageInterface::LANGCODE_NOT_SPECIFIED
    // and \Drupal\Core\Language\LanguageInterface::LANGCODE_NOT_APPLICABLE are
    // not returned from the language manager above.
    $value = [];
    if (isset($item->language)) {
      $name = isset($languages[$item->language->getId()]) ? $languages[$item->language->getId()]->getName() : $item->language->getId();
      $value = ['#plain_text' => $name];
    }
    return $value;
  }

}
