<?php

declare(strict_types=1);

namespace Drupal\image\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Defines an item list class for image fields.
 */
class ImageFieldItemList extends FileFieldItemList {

  /**
   * The properties that hold the editable content of an image item.
   */
  protected const array COMPARED_PROPERTIES = ['alt', 'target_id', 'title'];

  /**
   * {@inheritdoc}
   */
  public function equals(FieldItemListInterface $list_to_compare) {
    return $this->getComparableValue($this) === $this->getComparableValue($list_to_compare);
  }

  /**
   * Returns the value of an item list, reduced to its editable content.
   *
   * Dimensions are left out because the widget never submits them, and the
   * remaining properties are cast to string so that NULL and '' compare equal.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field item list to reduce.
   *
   * @return array
   *   The comparable value, keyed by delta.
   *
   * @see \Drupal\image\Plugin\Field\FieldType\ImageItem::preSave()
   * @see \Drupal\image\Plugin\Field\FieldWidget\ImageWidget::process()
   */
  protected function getComparableValue(FieldItemListInterface $items): array {
    $values = [];
    foreach ($items as $delta => $item) {
      $value = $item->getValue();
      foreach (static::COMPARED_PROPERTIES as $property) {
        $values[$delta][$property] = (string) ($value[$property] ?? '');
      }
    }
    return $values;
  }

}
