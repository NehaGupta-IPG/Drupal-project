<?php

namespace Drupal\entity_test\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\entity_test\TraversableObject;

/**
 * Plugin implementation of the 'any' field type.
 *
 * @FieldType(
 *   id = "any",
 *   label = @Translation("Any"),
 *   description = @Translation("An entity field for storing any values."),
 * )
 */
class AnyItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('any')
      ->setLabel(t('Value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (is_array($values)) {
      // The property definition causes the object to be in 'value' key.
      $values = reset($values);
    }
    // Use for denormalization.
    if (is_array($values)) {
      $values = new TraversableObject($values);
    }
    if (!$values instanceof TraversableObject) {
      $values = NULL;
    }
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'description' => 'The any item value.',
          'type' => 'blob',
          'not null' => TRUE,
          'serialize' => TRUE,
        ],
      ],
    ];
  }

}
