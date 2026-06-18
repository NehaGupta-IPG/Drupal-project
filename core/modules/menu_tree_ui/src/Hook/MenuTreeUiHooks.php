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
      $output .= '<dd>' . $this->t('When enabled, the module becomes the "Links" tab for each menu (Structure » Menus » Edit links), with the classic form kept as a "Links (no js)" fallback. The screen loads one level of the tree at a time and fetches deeper levels on demand, so it stays responsive on menus with thousands of links.') . '</dd>';
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
   * The menu listing's "Edit links" action lands on the link list. With the
   * tree present that list is the scalable tree editor, so point the action at
   * it (rather than menu_ui's classic tabledrag page). The classic page stays
   * reachable as the "Links (no js)" tab once there.
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
    $operations['edit']['url'] = Url::fromRoute('menu_tree_ui.edit', ['menu' => $entity->id()]);
  }

  /**
   * Implements hook_local_tasks_alter().
   *
   * With the tree present it becomes the "Links" tab (see
   * menu_tree_ui.links.task.yml); menu_ui's classic tabledrag overview is
   * demoted to the "Links (no js)" fallback tab just after it.
   *
   * @param array $local_tasks
   *   The local task plugin definitions, keyed by plugin ID.
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(array &$local_tasks): void {
    if (isset($local_tasks['entity.menu.links'])) {
      $local_tasks['entity.menu.links']['title'] = $this->t('Links (no js)');
      $local_tasks['entity.menu.links']['weight'] = 20;
    }
  }

}
