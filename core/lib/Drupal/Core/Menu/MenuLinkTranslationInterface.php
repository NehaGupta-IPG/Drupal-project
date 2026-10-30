<?php

namespace Drupal\Core\Menu;

/**
 * Defines an interface for exposing multilingual capabilities.
 */
interface MenuLinkTranslationInterface {

  /**
   * Determines if menu link has a translation.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return bool
   *   TRUE if menu link has a translation, FALSE if not.
   */
  public function hasTranslation($langcode) : bool;

}
