<?php

/**
 * @file
 * Post update functions for Content Block.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;

/**
 * Implements hook_removed_post_updates().
 */
function block_content_removed_post_updates(): array {
  return [
    'block_content_post_update_add_views_reusable_filter' => '9.0.0',
    'block_content_post_update_entity_changed_constraint' => '11.0.0',
    'block_content_post_update_move_custom_block_library' => '11.0.0',
    'block_content_post_update_block_library_view_permission' => '11.0.0',
    'block_content_post_update_sort_permissions' => '11.0.0',
    'block_content_post_update_revision_type' => '11.0.0',
  ];
}

/**
 * Removes the "area_text_custom" plugin from block content view configuration.
 */
function block_content_post_update_10301(?array &$sandbox = NULL): int {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view): bool {
    $changed = FALSE;

    foreach ($view->get('display') as $display_id => $display) {
      if (
        isset($display['display_options']['empty']['area_text_custom']) &&
        $display['display_options']['empty']['area_text_custom']['content'] === 'There are no content blocks available.'
      ) {
        unset($display['display_options']['empty']['area_text_custom']);
        $view->set('display.' . $display_id . '.display_options.empty.area_text_custom', NULL);
        $changed = TRUE;
      }
    }

    return $changed;
  });

  return 0;
}
