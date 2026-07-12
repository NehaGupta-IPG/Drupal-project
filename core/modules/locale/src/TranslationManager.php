<?php

namespace Drupal\locale;

use Drupal\Component\Gettext\PoItem;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\PreTranslatedPluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager as CoreTranslationManager;

/**
 * Plural index implementation.
 */
class TranslationManager extends CoreTranslationManager {

  use DependencySerializationTrait;

  public function __construct(LanguageDefault $default_language, protected PluralFormulaInterface $pluralFormula) {
    parent::__construct($default_language);
  }

  /**
   * {@inheritdoc}
   */
  public function translateString(TranslatableMarkup $translated_string) {
    if ($translated_string instanceof PreTranslatedPluralTranslatableMarkup) {
      $string = $translated_string->translatedString;
    }
    else {
      $string = $this->doTranslate($translated_string->getUntranslatedString(), $translated_string->getOptions());
    }
    if ($translated_string instanceof PluralTranslatableMarkup) {
      $count = $translated_string->getCount();

      $translated_array = explode(PoItem::DELIMITER, $translated_string);
      if ($count == 1 || count($translated_array) == 1) {
        // Singular form.
        return $translated_array[0];
      }

      $index = $this->getPluralIndex($translated_string->getCount(), $translated_string->getOptions()['langcode'] ?? NULL);

      // Fallback to second plural form.
      return $translated_array[$index] ?? $translated_array[1];
    }
    return $string;
  }

  /**
   * Returns plural form index for a specific number.
   *
   * The index is computed from the formula of this language.
   *
   * @param int $count
   *   Number to return plural for.
   * @param string|null $langcode
   *   Optional language code to translate to a language other than what is used
   *   to display the page.
   *
   * @return int
   *   The numeric index of the plural variant to use for this $langcode and
   *   $count combination or -1 if the language was not found or does not have a
   *   plural formula.
   */
  protected function getPluralIndex(int $count, ?string $langcode = NULL): int {
    $langcode = $langcode ?: $this->defaultLangcode;

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
      return $plural_formulas[$index] ?? $plural_formulas['default'];
    }
    // In case there is no plural formula for English (no imported translation
    // for English), use a default formula.
    elseif ($langcode == 'en') {
      return (int) ($count != 1);
    }
    // Otherwise, return -1 (unknown).
    else {
      return -1;
    }
  }

}
