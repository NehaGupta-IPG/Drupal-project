<?php

namespace Drupal\image;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Provides a BC layer for modules providing old configurations.
 *
 * @internal
 *   This class is only meant to fix outdated image configuration and its
 *   methods should not be invoked directly. It will be removed once all the
 *   deprecated methods have been removed.
 */
final class ImageConfigUpdater {

  /**
   * Add the image preload setting to image field formatter instances.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display
   *   The view display.
   *
   * @return bool
   *   Whether the display was updated.
   */
  public function processImagePreload(EntityViewDisplayInterface $view_display): bool {
    $changed = FALSE;

    foreach ($view_display->getComponents() as $field => $component) {
      if (isset($component['type'])
        && ($component['type'] === 'image')
        && !array_key_exists('preload', $component['settings']['image_loading'])
      ) {
        $component['settings']['image_loading'] = ['preload' => FALSE] + $component['settings']['image_loading'];
        $view_display->setComponent($field, $component);
        $changed = TRUE;
      }
    }

    return $changed;
  }

}
