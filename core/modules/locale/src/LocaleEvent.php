<?php

namespace Drupal\locale;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines a Locale event.
 */
class LocaleEvent extends Event {

  /**
   * The list of Language codes for updated translations.
   *
   * @var string[]
   */
  protected $langCodes;

  /**
   * Constructs a new LocaleEvent.
   *
   * @param array $lang_codes
   *   Language codes for updated translations.
   * @param array $lids
   *   (optional) List of string identifiers that have been updated / created.
   */
  public function __construct(
    array $lang_codes,
    protected array $lids = [],
  ) {
    $this->langCodes = $lang_codes;
  }

  /**
   * Returns the language codes.
   *
   * @return string[]
   *   $langCodes
   */
  public function getLangCodes() {
    return $this->langCodes;
  }

  /**
   * Returns the string identifiers.
   *
   * @return array
   *   $lids
   */
  public function getLids() {
    return $this->lids;
  }

}
