<?php

/**
 * @file
 * Post-update functions for the Shortcut module.
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\shortcut\Entity\Shortcut;

/**
 * Fix empty shortcut titles.
 */
function shortcut_post_update_fix_empty_titles(&$sandbox = NULL): TranslatableMarkup {
  if (!isset($sandbox['total'])) {
    $query = \Drupal::entityQuery('shortcut')
      ->accessCheck(FALSE);
    $sandbox['ids'] = $query->execute();
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;

    if ($sandbox['total'] == 0) {
      $sandbox['total'] = 1;
      $sandbox['progress'] = 1;
    }
  }

  $ids = \array_splice($sandbox['ids'], 0, $sandbox['total'], 50);
  $shortcuts = Shortcut::loadMultiple($ids);
  foreach ($shortcuts as $shortcut) {
    if (empty($shortcut->getTitle())) {
      $shortcut->setTitle('(' . t('Empty', [], ['langcode' => $shortcut->language()->getId()]) . ')');
      $shortcut->save();
    }
    $sandbox['progress'] += 1;
  }

  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['progress'] / $sandbox['total']);

  return new TranslatableMarkup('Processed Shortcut Entities (@count/@total)', [
    '@count' => $sandbox['progress'],
    '@total' => $sandbox['total'],
  ]);
}
