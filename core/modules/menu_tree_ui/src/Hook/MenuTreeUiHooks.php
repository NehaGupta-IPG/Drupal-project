<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\system\MenuInterface;

/**
 * Hooks for the menu_tree_ui module.
 */
final class MenuTreeUiHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_operation_alter().
   *
   * On the menu listing (/admin/structure/menu) this promotes the tree editor
   * to the primary "Edit menu" dropbutton action and demotes menu_ui's classic
   * form to "Edit menu (no JS)". The alter runs after hook_entity_operation
   * and EntityListBuilder::getDefaultOperations() have both merged their
   * contributions, so the 'edit' key is already present with menu_ui's title.
   *
   * Result (sorted by weight):
   *   0   "Edit menu"       → /admin/structure/menu-tree-ui/{menu}
   *   5   "Edit menu (no JS)" → /admin/structure/menu/manage/{menu}
   *   20  "Add link"
   *   100 "Delete menu"
   *
   * @param array $operations
   *   The merged operations array, keyed by operation ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the operations apply to.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    if (!$entity instanceof MenuInterface) {
      return;
    }
    if (!isset($operations['edit'])) {
      return;
    }
    // Demote the classic form — rename and push it just below the tree UI.
    $operations['edit']['title'] = $this->t('Edit menu (no JS)');
    $operations['edit']['weight'] = 5;

    // Promote the tree editor to the primary action.
    $operations['menu-tree-ui'] = [
      'title' => $this->t('Edit menu'),
      'url' => Url::fromRoute('menu_tree_ui.edit', ['menu' => $entity->id()]),
      'weight' => 0,
    ];
  }

}
