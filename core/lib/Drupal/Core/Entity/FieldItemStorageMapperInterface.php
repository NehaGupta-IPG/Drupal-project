<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Interface for field item classes that support storage mapping.
 *
 * Field item classes may control the mapping of properties to database columns.
 * Such field items must implement this interface. Drupal's default field
 * storage will further delegate field storage mapping calls from the entity
 * storage down to this interface.
 *
 * @see https://www.drupal.org/node/3377624
 * @see \Drupal\Core\Entity\Sql\StorageMapperInterface
 */
interface FieldItemStorageMapperInterface extends FieldItemInterface {

  /**
   * Maps columns on load.
   *
   * @param array $columns
   *   The columns to map.
   *
   * @return array|null
   *   The mapped field properties.
   */
  public static function mapColumnsOnLoad(array $columns): array;

  /**
   * Maps columns on save.
   *
   * @param array $properties
   *   The field properties to map.
   *
   * @return array
   *   The mapped columns.
   */
  public static function mapColumnsOnSave(array $properties): array;

}
