<?php

/**
 * @file
 * Post update functions for Content Block.
 */

use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
 * Set a default author for block content entities.
 */
function block_content_post_update_set_owner(&$sandbox = NULL): TranslatableMarkup {
  $blockContentStorage = \Drupal::entityTypeManager()
    ->getStorage('block_content');

  if (!isset($sandbox['total'])) {
    $query = $blockContentStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', NULL, 'IS NULL');
    $sandbox['ids'] = $query->execute();
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;

    // Handle the case of 0 block to process.
    if ($sandbox['total'] == 0) {
      $sandbox['total'] = 1;
      $sandbox['progress'] = 1;
    }
  }

  $ids = \array_splice($sandbox['ids'], 0, (int) Settings::get('entity_update_batch_size', 50));
  $tableMapping = $blockContentStorage->getTableMapping();

  $database = \Drupal::database();
  foreach ($ids as $id) {
    // Get the revision_user from the first revision of this block to use
    // as the author.
    $query = $database->select('block_content_revision', 'bcr')
      ->condition('id', $id);
    $query->addField('bcr', 'revision_user');
    $query->orderBy('revision_id', 'ASC');
    $query->range(0, 1);
    $uid = $query->execute()->fetchField();
    foreach ($tableMapping->getAllFieldTableNames('uid') as $tableName) {
      $database->update($tableName)
        ->fields(['uid' => $uid ?? 0])
        ->condition('id', $id)
        ->execute();
    }
    $sandbox['progress'] += 1;
  }

  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['progress'] / $sandbox['total']);

  return new TranslatableMarkup('Processed Block Content Entities (@count/@total)', [
    '@count' => $sandbox['progress'],
    '@total' => $sandbox['total'],
  ]);
}
