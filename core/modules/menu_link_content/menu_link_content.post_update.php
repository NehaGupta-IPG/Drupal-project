<?php

/**
 * @file
 * Post update functions for the Menu link content module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Implements hook_removed_post_updates().
 */
function menu_link_content_removed_post_updates(): array {
  return [
    'menu_link_content_post_update_make_menu_link_content_revisionable' => '9.0.0',
  ];
}

/**
 * Updates menu_link_content form displays to use link_autocomplete widget.
 */
function menu_link_content_post_update_use_link_autocomplete_widget(&$sandbox = []): void {
  /** @var \Drupal\Core\Config\Entity\ConfigEntityUpdater $updater */
  $updater = \Drupal::classResolver(ConfigEntityUpdater::class);

  $updater->update($sandbox, 'entity_form_display', function (EntityFormDisplayInterface $form_display): bool {
    // Only process menu_link_content entity form displays.
    if ($form_display->getTargetEntityTypeId() !== 'menu_link_content') {
      return FALSE;
    }

    // Check if the 'link' field component exists.
    $component = $form_display->getComponent('link');
    if (!$component) {
      return FALSE;
    }

    // Only update if it's not already using link_autocomplete.
    if ($component['type'] === 'link_autocomplete') {
      return FALSE;
    }

    // Update the widget type to link_autocomplete.
    $component['type'] = 'link_autocomplete';
    // Use default settings for the new widget.
    // Default settings include placeholder_url, placeholder_title from parent,
    // and auto_link_text = FALSE.
    $component['settings'] = [
      'placeholder_url' => '',
      'placeholder_title' => '',
      'auto_link_text' => FALSE,
    ];
    $form_display->setComponent('link', $component);

    return TRUE;
  });
}
