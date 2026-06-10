<?php

namespace Drupal\Core\StringTranslation;

// cspell:ignore bajt bajty bajtów
/**
 * Interface for providing the plural index.
 *
 * The plural index is calculated from the plural formula of the language.
 * Here is an example of a Polish translation of core.
 * @code
 * msgid "1 byte"
 * msgid_plural "@count bytes"
 * msgstr[0] "1 bajt"
 * msgstr[1] "@count bajty"
 * msgstr[2] "@count bajtów"
 * @endcode
 * The index in this case is 0, 1, or 2.
 * This is calculated from the Plural-Forms at the top of the file.
 * For Polish nplurals=3;
 * phpcs:ignore Drupal.Files.LineLength
 * plural=((n==1)?(0):(((((n%10)>=2)&&((n%10)<=4))&&(((n%100)<10)||((n%100)>=20)))?(1):2));\n
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
