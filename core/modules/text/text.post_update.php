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
 * Populates the new `exclude_html_tags` setting on text_trimmed formatters.
 */
function text_post_update_add_exclude_html_tags_setting(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function ($display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      $changed = FALSE;
      foreach ($display->getComponents() as $name => $component) {
        if (isset($component['type']) && in_array($component['type'], ['text_trimmed', 'text_summary_or_trimmed'], TRUE) && !isset($component['settings']['exclude_html_tags'])) {
          $component['settings']['exclude_html_tags'] = TRUE;
          $display->setComponent($name, $component);
          $changed = TRUE;
        }
      }
      return $changed;
    });
}
