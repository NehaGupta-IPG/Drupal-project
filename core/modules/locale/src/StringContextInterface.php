<?php

namespace Drupal\locale;

/**
 * Defines the locale string context interface.
 */
interface StringContextInterface {

  /**
   * Loads list of all available string translation contexts.
   *
   * @return array
   *   Array of context strings.
   */
  public function getContexts(): array;

  /**
   * Loads list of all available string locations.
   *
   * @return array
   *   Array of location strings in "type: name" format.
   */
  public function getDistinctLocations(): array;

}
