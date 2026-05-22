<?php

namespace Drupal\mongodb\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\CreatedItem as CoreCreatedItem;

/**
 * Overriding the fieldtype plugin "created".
 */
class CreatedItem extends CoreCreatedItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'date',
        ],
      ],
    ];
  }

}
