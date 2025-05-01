<?php

namespace Drupal\Core\Locale;

/**
 * Defines a common interface for country managers.
 */
interface CountryManagerInterface {

  /**
   * Returns a list of country code => country name pairs.
   *
   * @return array
   *   An array of country code => country name pairs.
   */
  public function getList();

  /**
   * Get an array of country code => country name pairs.
   *
   * @param string $language_id
   *   The language id; for example 'en', 'fr' or 'de'.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of country code => country name pairs.
   *
   * @see \Drupal\Core\Locale\CountryManager::getStandardList()
   */
  public function getLocalizedList(string $language_id) : array;

}
