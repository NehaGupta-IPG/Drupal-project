<?php

/**
 * @file
 * Post-update functions for Datetime Range module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Implements hook_removed_post_updates().
 */
function datetime_range_removed_post_updates(): array {
  return [
    'datetime_range_post_update_translatable_separator' => '9.0.0',
    'datetime_range_post_update_views_string_plugin_id' => '9.0.0',
    'datetime_range_post_update_from_to_configuration' => '11.0.0',
  ];

}

/**
 * Add the "Year Range" setting to the default widget.
 */
function datetime_range_post_update_default_date_range(?array &$sandbox = []): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_form_display', function (EntityFormDisplayInterface $form_display): bool {
    $changed = FALSE;
    foreach ($form_display->getComponents() as $field => $component) {
      if (is_array($component)
        && array_key_exists('type', $component)
        && ($component['type'] === 'daterange_datelist')
        && !array_key_exists('year_range', $component['settings'])) {
        $component['settings']['year_range'] = '1900:2050';
        $form_display->setComponent($field, $component);
        $changed = TRUE;
      }
    }

    return $changed;
  });
}
