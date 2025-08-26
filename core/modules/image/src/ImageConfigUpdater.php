<?php

namespace Drupal\image;

use Drupal\Core\Image\ImageResizePolicy;
use Drupal\field\FieldConfigInterface;

/**
 * Provides a BC layer for modules providing old configurations.
 *
 * @internal
 */
class ImageConfigUpdater {

  /**
   * Flag determining whether deprecations should be triggered.
   *
   * @var bool
   */
  protected bool $deprecationsEnabled = TRUE;

  /**
   * Stores which deprecations were triggered.
   *
   * @var array
   */
  protected array $triggeredDeprecations = [];

  /**
   * Sets the deprecations enabling status.
   *
   * @param bool $enabled
   *   Whether deprecations should be enabled.
   */
  public function setDeprecationsEnabled(bool $enabled): void {
    $this->deprecationsEnabled = $enabled;
  }

  /**
   * Performs the required update.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field to update.
   *
   * @return bool
   *   Whether the field was updated.
   */
  public function updateField(FieldConfigInterface $field): bool {
    $changed = FALSE;
    if ($this->needsResizePolicySettingUpdate($field)) {
      $field->setSetting('resize_policy', ImageResizePolicy::ResizeLargerImages->value);
      $changed = TRUE;
    }
    return $changed;
  }

  /**
   * Checks if the field still misses 'resize_policy' setting.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field to update.
   *
   * @return bool
   *   TRUE if the field has not the new setting.
   */
  public function needsResizePolicySettingUpdate(FieldConfigInterface $field): bool {
    $needs_update = FALSE;
    if ($field->getType() === 'image' && $field->getSetting('resize_policy') === NULL) {
      $needs_update = TRUE;

      $deprecations_triggered = &$this->triggeredDeprecations['3325551'][$field->id()];
      if ($this->deprecationsEnabled && !$deprecations_triggered) {
        $deprecations_triggered = TRUE;
        @trigger_error('Image fields without "resize_policy" setting is deprecated in drupal:11.1.0 and will be removed in drupal:12.0.0. This new setting default value is "resize_larger_images". Profile, module and theme provided configuration should be updated. See https://www.drupal.org/node/3467820', E_USER_DEPRECATED);
      }
    }

    return $needs_update;
  }

}
