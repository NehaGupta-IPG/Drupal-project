<?php

/**
 * @file
 * Post update functions for Responsive Image.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\responsive_image\ResponsiveImageConfigUpdater;

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
 * Add the image preload setting to image field formatter instances.
 */
function responsive_image_post_update_preload_setting(array &$sandbox = []): void {
  $responsive_image_config_updater = \Drupal::classResolver(ResponsiveImageConfigUpdater::class);
  assert($responsive_image_config_updater instanceof ResponsiveImageConfigUpdater);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (EntityViewDisplayInterface $view_display) use ($responsive_image_config_updater): bool {
    return $responsive_image_config_updater->processResponsiveImagePreload($view_display);
  });
}
