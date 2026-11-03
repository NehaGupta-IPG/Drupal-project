<?php

/**
 * @file
 * Post update functions for Configuration Translation.
 */

use Drupal\Component\Gettext\PoItem;

/**
 * Removes plural configuration translations without text.
 */
function config_translation_post_update_remove_empty_plural_translations(): void {
  // Removes the values with delimiters only, and the arrays emptied by that.
  $remove = function (array $data) use (&$remove): array {
    foreach ($data as $key => $value) {
      if (is_array($value) && $value) {
        $value = $remove($value);
        if ($value) {
          $data[$key] = $value;
        }
        else {
          unset($data[$key]);
        }
      }
      elseif (is_string($value) && $value !== '' && trim($value, PoItem::DELIMITER) === '') {
        unset($data[$key]);
      }
    }
    return $data;
  };

  $storage = \Drupal::service('config.storage');
  $override_factory = \Drupal::service('language.config_factory_override');
  foreach ($storage->getAllCollectionNames() as $collection) {
    if (!str_starts_with($collection, 'language.')) {
      continue;
    }
    $collection_storage = $storage->createCollection($collection);
    foreach ($collection_storage->readMultiple($collection_storage->listAll()) as $name => $data) {
      $translation = $remove($data);
      if ($translation === $data) {
        continue;
      }
      $override = $override_factory->getOverride(substr($collection, strlen('language.')), $name);
      if ($translation) {
        $override->setData($translation)->save();
      }
      else {
        $override->delete();
      }
    }
  }
}
