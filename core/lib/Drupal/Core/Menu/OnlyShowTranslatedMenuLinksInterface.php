<?php

namespace Drupal\Core\Menu;

/**
 * Defines an interface for menu manipulators to filter menu trees by language.
 */
interface OnlyShowTranslatedMenuLinksInterface {

  /**
   * Determines if a menu tree should hide translated items.
   *
   * @return bool
   *   TRUE if menu tree should filter out non-translated items, FALSE if not.
   */
  public function hideUntranslated(): bool;

}
