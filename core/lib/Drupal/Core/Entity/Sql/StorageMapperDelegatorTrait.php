<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Sql;

use Drupal\Core\Entity\FieldItemStorageMapperInterface;

/**
 * Delegate StorageMapperInterface methods to the field item class.
 */
trait StorageMapperDelegatorTrait {

  /**
   * Get the field item class.
   *
   * @return string
   *   The field item class.
   */
  abstract protected function getFieldItemClass();

  /**
   * Maps columns on load.
   *
   * @param array $columns
   *   The field properties to map.
   *
   * @return array|null
   *   The mapped field properties, or NULL to fall back to default mapping.
   */
  public function mapColumnsOnLoad(array $columns): ?array {
    $fieldItemClass = $this->getFieldItemClass();
    if (is_subclass_of($fieldItemClass, FieldItemStorageMapperInterface::class)) {
      return $fieldItemClass::mapColumnsOnLoad($columns);
    }
    return NULL;
  }

  /**
   * Maps columns on save.
   *
   * @param array $columns
   *   The columns to map.
   *
   * @return array|null
   *   The mapped columns, or NULL to fall back to default mapping.
   */
  public function mapColumnsOnSave(array $columns): ?array {
    $fieldItemClass = $this->getFieldItemClass();
    if (is_subclass_of($fieldItemClass, FieldItemStorageMapperInterface::class)) {
      return $fieldItemClass::mapColumnsOnSave($columns);
    }
    return NULL;
  }

}
