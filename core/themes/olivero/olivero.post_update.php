<?php

/**
 * @file
 * Post update functions for Olivero.
 */

/**
 * Implements hook_removed_post_updates().
 */
function olivero_removed_post_updates(): array {
  return [
    'olivero_post_update_add_olivero_primary_color' => '11.0.0',
  ];
}

/**
 * Sets the `comment_form_position` value of Olivero's theme settings.
 */
function olivero_post_update_add_comment_form_position(): void {
  \Drupal::configFactory()->getEditable('olivero.settings')
    ->set('comment_form_position', 'before')
    ->save(TRUE);
}
