<?php

namespace Drupal\Core\StringTranslation;

/**
 * Interface for providing the plural index.
 */
interface PluralIndexInterface {

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
  public function getPluralIndex(int $count, ?string $langcode = NULL): int;

}
