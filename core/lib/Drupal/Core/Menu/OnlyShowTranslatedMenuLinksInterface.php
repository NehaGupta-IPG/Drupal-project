<?php

namespace Drupal\Core\Menu;

/**
 * Defines an interface for menu manipulators to decide if the menu tree should be filtered by language.
 */
interface OnlyShowTranslatedMenuLinksInterface {

  /**
   * Determines if a menu tree should hide translated items.
   *
   * @return bool
   *   TRUE if menu tree should filter out unstraslated items, FALSE if not.
   */
  public function hideUntranslated(): bool;

}
