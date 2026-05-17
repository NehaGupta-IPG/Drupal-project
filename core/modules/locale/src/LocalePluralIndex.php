<?php

namespace Drupal\locale;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\PluralIndexInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;

/**
 * Plural index implementation.
 */
class LocalePluralIndex implements PluralIndexInterface {

  use DependencySerializationTrait;

  /**
   * The plural indexes.
   *
   * @var array
   */
  protected array $pluralIndexes = [];

  public function __construct(protected PluralFormulaInterface $pluralFormula, protected LanguageManagerInterface $languageManager) {
  }
  /**
   * {@inheritdoc}
   */
  public function getPluralIndex(int $count, ?string $langcode = NULL): int {
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
        $this->pluralIndexes[$langcode][$count] = $plural_formulas[$index] ?? $plural_formulas['default'];
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
   * Reset plural index.
   */
  public function reset() {
    $this->pluralIndexes = [];
  }

}
