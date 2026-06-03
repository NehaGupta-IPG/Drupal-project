<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\system\MenuInterface;

/**
 * Hooks for the menu_tree_ui module.
 */
final class MenuTreeUiHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.menu_tree_ui') {
      $output = '';
      $output .= '<h2>' . $this->t('About') . '</h2>';
      $output .= '<p>' . $this->t('The Menu tree UI module provides a scalable, lazy-loaded screen for editing menus that contain large numbers of links, where the core Menu UI form becomes slow or runs out of memory. For more information, see the <a href=":documentation">online documentation for the Menu tree UI module</a>.', [
        ':documentation' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/menu-tree-ui-module',
      ]) . '</p>';
      $output .= '<h2>' . $this->t('Uses') . '</h2>';
      $output .= '<dl>';
      $output .= '<dt>' . $this->t('Editing a menu at scale') . '</dt>';
      $output .= '<dd>' . $this->t('When enabled, the module adds an "Edit menu" operation to each menu on the Menus administration page (Structure » Menus). The screen loads one level of the tree at a time and fetches deeper levels on demand, so it stays responsive on menus with thousands of links.') . '</dd>';
      $output .= '<dt>' . $this->t('Reordering links') . '</dt>';
      $output .= '<dd>' . $this->t('Links can be reordered and re-nested with pointer drag-and-drop or entirely from the keyboard. A "Keyboard commands" dialog at the top of the screen lists the available shortcuts.') . '</dd>';
      $output .= '<dt>' . $this->t('Searching') . '</dt>';
      $output .= '<dd>' . $this->t('A search box filters the menu by link title and jumps to the matching link in the tree.') . '</dd>';
      $output .= '</dl>';
      return $output;
    }
    return NULL;
  }

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
