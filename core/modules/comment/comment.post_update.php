<?php

/**
 * @file
 * Post update functions for the comment module.
 */

use Drupal\comment\CommentViewsConfigUpdater;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\ViewEntityInterface;

/**
 * Implements hook_removed_post_updates().
 */
function comment_removed_post_updates(): array {
  return [
    'comment_post_update_enable_comment_admin_view' => '9.0.0',
    'comment_post_update_add_ip_address_setting' => '9.0.0',
  ];
}

/**
 * Updates numeric filters on comment field status columns in views.
 */
function comment_post_update_views_status_filter(array &$sandbox): ?TranslatableMarkup {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return NULL;
  }
  $updater = \Drupal::classResolver(CommentViewsConfigUpdater::class);
  $sandbox['comment_skipped_filters'] ??= [];
  $message = \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($updater, &$sandbox): bool {
    $skipped = [];
    $changed = $updater->processStatusFilters($view, $skipped);
    foreach ($skipped as $filter) {
      $sandbox['comment_skipped_filters'][] = $view->id() . ':' . $filter;
    }
    return $changed;
  });
  if ($sandbox['#finished'] == 1 && $sandbox['comment_skipped_filters']) {
    return new TranslatableMarkup('The following comment status filters could not be updated and must be reviewed manually: %filters.', [
      '%filters' => implode(', ', $sandbox['comment_skipped_filters']),
    ]);
  }
  return $message;
}
