<?php

/**
 * @file
 * Contains post update hooks for the text module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Implements hook_removed_post_updates().
 */
function text_removed_post_updates(): array {
  return [
    'text_post_update_add_required_summary_flag' => '9.0.0',
    'text_post_update_add_required_summary_flag_form_display' => '10.0.0',
    'text_post_update_allowed_formats' => '11.0.0',
  ];
}

/**
 * Installs text_with_summary when widget is used.
 */
function text_post_update_install_text_with_summary(): void {
  if (\Drupal::moduleHandler()->moduleExists('text_with_summary')) {
    return;
  }
  $storages = \Drupal::entityTypeManager()
    ->getStorage('field_storage_config')
    ->loadByProperties(['type' => 'text_with_summary']);
  if ($storages !== []) {
    \Drupal::service('module_installer')->install(['text_with_summary']);
  }
}

/**
 * Update potential filed dependencies
 */
function text_post_update_recalculate_text_with_summary_dependencies(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'field_storage_config');
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'field_config');
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_form_display');
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display');

  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return;
  }
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view');
}
