<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for the menu_tree_ui module.
 *
 * @internal
 */
final class MenuTreeUiThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'menu_tree_ui_page' => [
        'variables' => [
          'menu' => NULL,
          'top_level' => [],
          'search_action' => '',
          'children_action' => '',
          // The placeholder for the CSRF header token; see
          // \Drupal\treegrid\TreegridCsrfToken::addPlaceholder().
          'csrf_token' => '',
          // URL the column-update client posts checkbox changes to.
          'column_update_url' => '',
          // URL the reorder feature posts moves to.
          'move_url' => '',
          // URL of the "Add link" form for this menu, with a destination
          // back to this page.
          'add_link_url' => '',
          // Array of column header info: keyed by plugin ID, each entry has
          // 'id' (string) and 'label' (TranslatableMarkup).
          'columns' => [],
          // Total number of top-level links in the menu.
          'total' => 0,
          // Number of top-level links rendered on the server (the page size).
          'page_size' => 0,
          // Whether the top level was truncated to the first page.
          'truncated' => FALSE,
          // Whether the menu has pending (unsaved) working-copy moves.
          'has_changes' => FALSE,
          // Whether reordering is off because a link in the menu has a
          // pending revision in a workspace.
          'reorder_blocked' => FALSE,
          // Whether another user holds the working copy.
          'locked' => FALSE,
          // Display name of the user holding the working copy, when locked.
          'lock_owner' => '',
          // Unix timestamp the working copy was last updated, when locked.
          'lock_updated' => 0,
          // URL of the break-lock confirmation form, when locked.
          'break_lock_url' => '',
        ],
      ],
      'menu_tree_ui_row' => [
        'variables' => [
          'element' => NULL,
          // Parent link plugin ID, or '' for the top level. Carries the tree
          // hierarchy in the flat <tr> markup (tables cannot nest rows).
          'parent_id' => '',
          // The link's destination URL (string) and whether to render the title
          // as a link to it (FALSE for <nolink> and the like).
          'url' => '',
          'linked' => FALSE,
          'depth' => 0,
          'pos_in_set' => 1,
          'set_size' => 1,
          // Whether the row can be dragged / keyboard-reordered. See
          // \Drupal\menu_tree_ui\MenuLinkMovability.
          'movable' => FALSE,
          // Keyed by column plugin ID, each entry is a cell render array as
          // returned by TreegridColumnInterface::buildCell(). The operations
          // dropbutton is one such column, sorted last.
          'columns' => [],
        ],
      ],
      'menu_tree_ui_search_results' => [
        'variables' => [
          'results' => [],
          'query' => '',
        ],
      ],
    ];
  }

}
