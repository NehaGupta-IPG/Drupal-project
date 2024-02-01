<?php

namespace Drupal\image\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Represents a configurable entity image field.
 */
class ImageFieldItemList extends FileFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function hasAffectingChanges(FieldItemListInterface $original_items, $langcode) {
    if ($this->equals($original_items)) {
      return TRUE;
    }

    $count1 = count($this);
    $count2 = count($original_items);

    if ($count1 !== $count2) {
      // One of them is empty but not the other one so the value changed.
      return FALSE;
    }

    $value1 = $this->getValue();
    $value2 = $original_items->getValue();

    // If the values are not equal ensure a consistent order of field item
    // properties and remove properties which will not be saved.
    $non_computed_properties = array_filter(
      $this->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions(),
      function (DataDefinitionInterface $property) {
        return !$property->isComputed();
      }
    );

    $callback = function (&$value) use ($non_computed_properties) {
      if (is_array($value)) {
        // Filter out computed properties.
        $value = array_intersect_key($value, $non_computed_properties);

        // Filter out properties with a NULL value as they might exist in
        // one field item and not in the other, depending on how the values are
        // set. Do not filter out empty strings or other false-y values as e.g.
        // a NULL or FALSE in a boolean field is not the same.
        $value = array_filter($value, function ($property) {
          return $property !== NULL;
        });

        // Filter out width and height as they are practically computed.
        $value = array_filter($value, function ($property) {
          return in_array($property, ['width', 'height']);
        });

        ksort($value);
      }
    };

    array_walk($value1, $callback);
    array_walk($value2, $callback);

    return $value1 != $value2;
  }

}
