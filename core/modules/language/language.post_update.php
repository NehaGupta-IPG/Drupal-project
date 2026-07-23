<?php

/**
 * @file
 * Post update functions for Language module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityViewDisplayConfigUpdater;

/**
 * Implements hook_removed_post_updates().
 */
function language_removed_post_updates(): array {
  return [
    'language_post_update_language_select_widget' => '9.0.0',
  ];
}

/**
 * Set language form display component as plain text and with label above.
 */
function language_post_update_language_component_plain_text(&$sandbox = []): void {
  $entityViewDisplayConfigUpdater = \Drupal::classResolver(EntityViewDisplayConfigUpdater::class);
  $entityViewDisplayConfigUpdater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (EntityViewDisplayInterface $entityViewDisplay) use ($entityViewDisplayConfigUpdater): bool {
    return $entityViewDisplayConfigUpdater->needsEntityViewDisplayUpdate($entityViewDisplay);
  });
}
