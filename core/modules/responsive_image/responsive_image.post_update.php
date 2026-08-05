<?php

/**
 * @file
 * Post update functions for Responsive Image.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Implements hook_removed_post_updates().
 */
function responsive_image_removed_post_updates(): array {
  return [
    'responsive_image_post_update_recreate_dependencies' => '9.0.0',
    'responsive_image_post_update_order_multiplier_numerically' => '11.0.0',
    'responsive_image_post_update_image_loading_attribute' => '11.0.0',
  ];
}

/**
 * Add a 'fetchpriority' key to responsive image formatter settings.
 */
function responsive_image_post_update_add_fetchpriority_setting(array &$sandbox = []): void {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (EntityViewDisplayInterface $display): bool {
    $changed = FALSE;
    foreach ($display->getComponents() as $name => $component) {
      if (($component['type'] ?? NULL) === 'responsive_image' && !array_key_exists('fetchpriority', $component['settings']['image_loading'] ?? [])) {
        $component['settings']['image_loading']['fetchpriority'] = NULL;
        $display->setComponent($name, $component);
        $changed = TRUE;
      }
    }
    return $changed;
  };
  $config_entity_updater->update($sandbox, 'entity_view_display', $callback);
}
