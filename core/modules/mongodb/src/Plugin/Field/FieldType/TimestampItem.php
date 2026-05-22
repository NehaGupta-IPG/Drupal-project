<?php

namespace Drupal\mongodb\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem as CoreTimestampItem;

/**
 * Overriding the fieldtype plugin "timestamp".
 */
class TimestampItem extends CoreTimestampItem {

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
