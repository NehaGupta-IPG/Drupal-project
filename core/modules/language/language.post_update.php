<?php

/**
 * @file
 * Post update functions for Language module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\language\EntityViewDisplayConfigUpdater;

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
  /** @var \Drupal\language\EntityViewDisplayConfigUpdater $language_config_updater */
  $language_config_updater = \Drupal::classResolver(EntityViewDisplayConfigUpdater::class);
  $language_config_updater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (EntityViewDisplayInterface $entityViewDisplay) use ($language_config_updater): bool {
    return $language_config_updater->needsEntityViewDisplayUpdate($entityViewDisplay);
  });
}
