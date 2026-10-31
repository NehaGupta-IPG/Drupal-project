<?php

namespace Drupal\field_ui\Form;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;

/**
 * Provides common functionality for adding or re-using a field.
 */
trait FieldStorageCreationTrait {

  /**
   * Configures the field for the default form mode.
   *
   * @param string $field_name
   *   The field name.
   * @param array[] $widget_settings
   *   (optional) Array of widget settings, keyed by form mode. Defaults to an
   *   empty array.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function configureEntityFormDisplay(string $field_name, array $widget_settings = []) {
    // For a new field, only $mode = 'default' should be set. Use the
    // preconfigured or default widget and settings. The field will not appear
    // in other form modes until it is explicitly configured.
    foreach ($widget_settings as $mode => $options) {
      $form_display = $this->entityDisplayRepository->getFormDisplay($this->entityTypeId, $this->bundle, $mode);
      // Do not copy over the widget settings if the display is not enabled, or
      // if the display is new and not the default display mode.
      if ($form_display->status() && (!$form_display->isNew() || $form_display->getMode() === EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE)) {
        $form_display->setComponent($field_name, $options)->save();
      }
    }

    if (empty($widget_settings)) {
      $this->entityDisplayRepository->getFormDisplay($this->entityTypeId, $this->bundle)
        ->setComponent($field_name, [])
        ->save();
    }
  }

  /**
   * Configures the field for the default view mode.
   *
   * @param string $field_name
   *   The field name.
   * @param array[] $formatter_settings
   *   (optional) An array of settings, keyed by view mode. Only the 'type' key
   *   of the inner array is used, and the value should be the plugin ID of a
   *   formatter. Defaults to an empty array.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function configureEntityViewDisplay(string $field_name, array $formatter_settings = []) {
    // For a new field, only $mode = 'default' should be set. Use the
    // preconfigured or default formatter and settings. The field stays hidden
    // for other view modes until it is explicitly configured.
    foreach ($formatter_settings as $mode => $options) {
      $view_display = $this->entityDisplayRepository->getViewDisplay($this->entityTypeId, $this->bundle, $mode);
      // Do not copy over the formatter settings if the display is not enabled,
      // or if the display is new and not the default display mode.
      if ($view_display->status() && (!$view_display->isNew() || $view_display->getMode() === EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE)) {
        $view_display->setComponent($field_name, $options)->save();
      }
    }

    if (empty($formatter_settings)) {
      $this->entityDisplayRepository->getViewDisplay($this->entityTypeId, $this->bundle)
        ->setComponent($field_name, [])
        ->save();
    }
  }

}
