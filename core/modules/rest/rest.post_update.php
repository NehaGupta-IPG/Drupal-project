<?php

/**
 * @file
 * Post update functions for Rest.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewsConfigUpdater;

/**
 * Implements hook_removed_post_updates().
 */
function rest_removed_post_updates(): array {
  return [
    'rest_post_update_create_rest_resource_config_entities' => '9.0.0',
    'rest_post_update_resource_granularity' => '9.0.0',
    'rest_post_update_161923' => '9.0.0',
    'rest_post_update_delete_settings' => '10.0.0',
  ];
}

/**
 * Add pager option to serializer style displays.
 */
function rest_post_update_add_serializer_pager(?array &$sandbox = NULL): void {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return;
  }
  /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
  $view_config_updater = \Drupal::classResolver(ViewsConfigUpdater::class);
  $view_config_updater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($view_config_updater): bool {
    return $view_config_updater->needsSerializerPagerUpdate($view);
  });
}
