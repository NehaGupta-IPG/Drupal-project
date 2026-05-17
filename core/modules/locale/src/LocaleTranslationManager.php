<?php

namespace Drupal\locale;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;

/**
 * Decorates translation manager to get plural index from locale database.
 *
 * @see \Drupal\Core\StringTranslation\TranslationManager
 */
class LocaleTranslationManager extends TranslationManager {

  use DependencySerializationTrait;

  /**
   * The decorated translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $decoratedManager;

  /**
   * Plural formula service.
   *
   * @var \Drupal\locale\PluralFormulaInterface
   */
  protected $pluralFormula;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The plural indexes.
   *
   * @var array
   */
  protected $pluralIndexes = [];

  /**
   * LocaleTranslationManager constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationManager $decorated_manager
   *   The translation manager to decorate.
   * @param \Drupal\locale\PluralFormulaInterface $plural_formula
   *   The plural formula service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(TranslationManager $decorated_manager, PluralFormulaInterface $plural_formula, LanguageManagerInterface $language_manager) {
    $this->decoratedManager = $decorated_manager;
    $this->pluralFormula = $plural_formula;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function addTranslator(TranslatorInterface $translator, $priority = 0) {
    return $this->decoratedManager->addTranslator($translator, $priority);
  }

  /**
   * {@inheritdoc}
   */
  public function translate($string, array $args = [], array $options = []) {
    return $this->decoratedManager->translate($string, $args, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function translateString(TranslatableMarkup $translated_string) {
    return $this->decoratedManager->translateString($translated_string);
  }

  /**
   * {@inheritdoc}
   */
  public function formatPlural($count, $singular, $plural, array $args = [], array $options = []) {
    return $this->decoratedManager->formatPlural($count, $singular, $plural, $args, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralIndex($count, $langcode = NULL) {
    $langcode = $langcode ?: $this->languageManager->getCurrentLanguage()->getId();

    if (!isset($this->pluralIndexes[$langcode][$count])) {
      // Retrieve and statically cache the plural formulas for all languages.
      $plural_formulas = $this->pluralFormula->getFormula($langcode);

      // If there is a plural formula for the language, evaluate it for the
      // given $count and statically cache the result for the combination of
      // language and count, since the result will always be identical.
      if (!empty($plural_formulas)) {
        // Plural formulas are stored as an array for 0-199. 100 is the highest
        // modulo used but storing 0-99 is not enough because below 100 we often
        // find exceptions (1, 2, etc).
        $index = $count > 199 ? 100 + ($count % 100) : $count;
        $this->pluralIndexes[$langcode][$count] = $plural_formulas[$index] ?: $plural_formulas['default'];
      }
      // In case there is no plural formula for English (no imported translation
      // for English), use a default formula.
      elseif ($langcode == 'en') {
        $this->pluralIndexes[$langcode][$count] = (int) ($count != 1);
      }
      // Otherwise, return -1 (unknown).
      else {
        $this->pluralIndexes[$langcode][$count] = -1;
      }
    }
    return $this->pluralIndexes[$langcode][$count];
  }

  /**
   * Sets the default langcode.
   *
   * @param string $langcode
   *   A language code.
   */
  public function setDefaultLangcode($langcode) {
    $this->decoratedManager->setDefaultLangcode($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getStringTranslation($langcode, $string, $context) {
    return $this->decoratedManager->getStringTranslation($langcode, $string, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->decoratedManager->reset();
    $this->pluralIndexes = [];
  }

}
