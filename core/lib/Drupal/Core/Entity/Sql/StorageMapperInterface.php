<?php

namespace Drupal\Core\Entity\Sql;

/**
 * Interface for field storage definitions that support storage mapping.
 *
 * Field storage definitions may control the mapping of properties to storage
 * columns. Such definitions must implement this interface. Drupal's default
 * field storage attempts to further delegate to field item classes which
 * implement \Drupal\Core\Entity\FieldItemStorageMapperInterface.
 *
 * @see https://www.drupal.org/node/3377624
 * @see \Drupal\Core\Entity\Sql\SqlContentEntityStorage::saveToDedicatedTables
 * @see \Drupal\Core\Entity\Sql\StorageMapperDelegatorTrait
 * @see \Drupal\Core\Entity\FieldItemStorageMapperInterface
 */
interface StorageMapperInterface {

  /**
   * Maps columns on load.
   *
   * @param array $columns
   *   The columns to map.
   *
   * @return array|null
   *   The mapped columns, or NULL to fall back to default mapping.
   */
  public function mapColumnsOnLoad(array $columns): ?array;

  /**
   * Maps columns on save.
   *
   * @param array $columns
   *   The columns to map.
   *
   * @return array|null
   *   The mapped columns, or NULL to fall back to default mapping.
   */
  public function mapColumnsOnSave(array $columns): ?array;

}
