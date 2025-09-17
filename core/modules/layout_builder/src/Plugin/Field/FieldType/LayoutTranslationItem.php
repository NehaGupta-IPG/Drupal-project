<?php

namespace Drupal\layout_builder\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\layout_builder\Field\LayoutTranslationItemList;

/**
 * Plugin implementation of the 'layout_section' field type.
 *
 * @internal
 *   Plugin classes are internal.
 */
#[FieldType(
  id: 'layout_translation',
  label: new TranslatableMarkup('Layout Translation'),
  description: new TranslatableMarkup('Layout Translation'),
  no_ui: TRUE,
  cardinality: 1,
  list_class: LayoutTranslationItemList::class,
)]
class LayoutTranslationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('layout_translation')
      ->setLabel(new TranslatableMarkup('Layout Translation'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'value' => [
          'type' => 'blob',
          'size' => 'normal',
          'serialize' => TRUE,
        ],
      ],
    ];

    return $schema;
  }

}
