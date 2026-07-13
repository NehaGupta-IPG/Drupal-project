<?php

namespace Drupal\system\Menu;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Menu\InaccessibleMenuLink;
use Drupal\Core\Menu\MenuLinkTranslationInterface;
use Drupal\Core\Menu\MenuLinkTreeContextualManipulatorInterface;
use Drupal\Core\Menu\OnlyShowTranslatedMenuLinksInterface;

/**
 * Provides a menu link language manipulator.
 */
class LanguageMenuLinkTreeManipulator implements MenuLinkTreeContextualManipulatorInterface {

  use CacheableDependencyTrait;

  /**
   * LanguageMenuLinkManipulator constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(protected readonly LanguageManagerInterface $languageManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function process(array $tree, mixed $context): array {
    $current_language = $this->getCurrentLanguage()->getId();
    foreach ($tree as $key => $link) {
      if (!$link->link instanceof MenuLinkTranslationInterface) {
        continue;
      }

      if (($link->link->isTranslatable() || ($link->link->getEntity()->language()->isLocked() && !in_array($link->link->getEntity()->language()->getId(), $this->languageManager->getDefaultLockedLanguages()))) && !$link->link->hasTranslation($current_language)) {
        $access = new AccessResultForbidden();

        // Instead of removing the menu link, mark it inaccessible so that its
        // cacheable metadata bubbles up.
        // @see \Drupal\Core\Menu\DefaultMenuLinkTreeManipulators::checkAccess
        if ($tree[$key]->access instanceof AccessResultInterface) {
          $access = $tree[$key]->access->andIf($access);
        }
        $tree[$key]->access = $access;
        $tree[$key]->link = new InaccessibleMenuLink($link->link);
        $tree[$key]->subtree = [];
      }
      elseif ($link->hasChildren) {
        // Recursively call this method to filter out untranslated children.
        $tree[$key]->subtree = $this->process($link->subtree, $context);
      }
    }
    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(array $tree, mixed $context): bool {
    if ($context instanceof OnlyShowTranslatedMenuLinksInterface) {
      return $context->hideUntranslated();
    }
    return FALSE;
  }

  /**
   * Gets the current language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The current language.
   */
  protected function getCurrentLanguage(): LanguageInterface {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['languages:language_content'];
  }

}
