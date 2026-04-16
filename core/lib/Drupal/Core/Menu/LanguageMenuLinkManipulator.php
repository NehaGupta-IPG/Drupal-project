<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides a menu link language manipulator.
 */
class LanguageMenuLinkManipulator {

  public function __construct(protected LanguageManagerInterface $languageManager) {
  }

  /**
   * Hide menu links that do not have translation for the current language.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function filterLanguage(array $tree) : array {
    $current_language = $this->languageManager->getCurrentLanguage()->getId();

    foreach ($tree as $key => $link) {
      if ($link->link instanceof MenuLinkTranslationInterface) {
        // If the link is translatable, but has no translation, hide it.
        if ($link->link->isTranslatable() && !$link->link->hasTranslation($current_language)) {
          unset($tree[$key]);
        }
        elseif ($link->hasChildren) {
          // Recursively call this method to filter out untranslated children.
          $tree[$key]->subtree = $this->filterLanguage($link->subtree);
        }
      }
    }
    return $tree;
  }

}
