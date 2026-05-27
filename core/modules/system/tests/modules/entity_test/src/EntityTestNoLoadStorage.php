<?php

declare(strict_types=1);

namespace Drupal\entity_test;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Test storage class used to verify that no load operation is triggered.
 */
class EntityTestNoLoadStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    throw new \Drupal\Core\Entity\EntityStorageException('No load operation is supposed to happen.');
  }

}
