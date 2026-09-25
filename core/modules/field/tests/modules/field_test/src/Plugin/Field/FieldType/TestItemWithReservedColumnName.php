<?php

declare(strict_types=1);

namespace Drupal\field_test\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines a field item with a property named like a dedicated-table column.
 *
 * Mirrors a real-world case (the contrib "address" field type has its own
 * "langcode" property) used to make sure a field's own column names always
 * take precedence over the dedicated table's built-in reserved columns
 * (bundle, deleted, entity_id, revision_id, langcode).
 */
#[FieldType(
  id: "test_field_reserved_column",
  label: new TranslatableMarkup("Test field with a reserved column name"),
  default_widget: "test_field_widget",
  default_formatter: "field_test_default",
)]
class TestItemWithReservedColumnName extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Test integer value'))
      ->setRequired(TRUE);

    // Same property name as one of the dedicated table's own reserved
    // columns, and the same purpose as the "address" field type's own
    // "langcode" property.
    $properties['langcode'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('A langcode owned by the field itself'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'size' => 'medium',
        ],
        'langcode' => [
          'type' => 'varchar_ascii',
          'length' => 12,
        ],
      ],
      'indexes' => [
        'value' => ['value'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->value);
  }

}
