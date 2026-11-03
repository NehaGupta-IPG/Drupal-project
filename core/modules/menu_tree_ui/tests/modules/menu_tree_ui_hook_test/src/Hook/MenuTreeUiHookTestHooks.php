<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui_hook_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Hook implementations for menu_tree_ui_hook_test.
 */
final class MenuTreeUiHookTestHooks {

  public function __construct(
    private readonly StateInterface $state,
    private readonly MenuLinkManagerInterface $menuLinkManager,
  ) {}

  /**
   * Implements hook_menu_tree_ui_post_move().
   *
   * Re-parents a link once, after the configured link has been moved. The
   * state value is an array with the keys 'after' (the plugin ID whose move
   * triggers the change), 'link' (the plugin ID to re-parent) and 'parent'
   * (its new parent plugin ID).
   */
  #[Hook('menu_tree_ui_post_move')]
  public function postMove(string $menu_name, string $moved_id, string $new_parent_id, int $weight): void {
    $change = $this->state->get('menu_tree_ui_hook_test.change_parent_after_move');
    if (!is_array($change) || $change['after'] !== $moved_id) {
      return;
    }
    $this->state->delete('menu_tree_ui_hook_test.change_parent_after_move');
    $this->menuLinkManager->updateDefinition($change['link'], ['parent' => $change['parent']]);
  }

}
