<?php

declare(strict_types=1);

namespace Drupal\entity_test\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldItemStorageMapperInterface;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Field type where properties are mapped on load and save.
 */
#[FieldType(
  id: 'mapped_properties_test',
  label: new TranslatableMarkup('Mapped properties field (test)'),
  description: new TranslatableMarkup('A field containing mapped properties.'),
)]
class MappedPropertiesItem extends MapItem implements FieldItemStorageMapperInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
        ],
        'extracted_value' => [
          'type' => 'text',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mapColumnsOnLoad(array $columns): array {
    return [
      // This goes first so we can determine if it's overwritten.
      'extracted_value' => 'Extracted: ' . $columns['extracted_value'],
      ...$columns['value'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mapColumnsOnSave(array $properties): array {
    $extracted = $properties['extracted_value'];
    unset($properties['extracted_value']);
    return [
      'value' => $properties,
      'extracted_value' => $extracted,
    ];
  }

}
