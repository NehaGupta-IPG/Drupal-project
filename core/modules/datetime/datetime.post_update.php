<?php

/**
 * @file
 * Contains post-update hooks for Datetime.
 */

declare(strict_types = 1);

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Add the "Year Range" setting to the default widget.
 */
function datetime_post_update_default_year_range(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_form_display', function (EntityFormDisplayInterface $form_display): bool {
    $changed = FALSE;
    foreach ($form_display->getComponents() as $field => $component) {
      if (is_array($component)
        && array_key_exists('type', $component)
        && ($component['type'] === 'datetime_datelist')
        && !array_key_exists('year_range', $component['settings'])) {
        $component['settings']['year_range'] = '1900:2050';
        $form_display->setComponent($field, $component);
        $changed = TRUE;
      }
    }

    return $changed;
  });
}
