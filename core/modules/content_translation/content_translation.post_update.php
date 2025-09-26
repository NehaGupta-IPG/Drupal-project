<?php

/**
 * @file
 * Post update functions for Content Translation module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Removes obsolete extra field from form displays.
 */
function content_translation_post_update_remove_extra_field(&$sandbox) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'entity_form_display', function (EntityFormDisplay $display) {
      $update = FALSE;
      // Use config directly because removeComponent() leaves components in the
      // hidden property.
      $components = $display->get('content');
      if (isset($components['translation'])) {
        unset($components['translation']);
        $display->set('content', $components);
        $update = TRUE;
      }
      $components = $display->get('hidden');
      if (isset($components['translation'])) {
        unset($components['translation']);
        $display->set('hidden', $components);
        $update = TRUE;
      }

      return $update;
    });
}
