<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel\mongodb;

use Drupal\KernelTests\Core\Database\SchemaUniquePrefixedKeysIndexTestBase;

/**
 * Tests adding UNIQUE keys to tables.
 *
 * @group Database
 */
class SchemaUniquePrefixedKeysIndexTest extends SchemaUniquePrefixedKeysIndexTestBase {

  /**
   * {@inheritdoc}
   */
  public function testCreateTable(): void {
    $this->markTestSkipped('MongoDB does not support partial unique key indexes.');
  }

  /**
   * {@inheritdoc}
   */
  public function testAddUniqueKey(): void {
    $this->markTestSkipped('MongoDB does not support partial unique key indexes.');
  }

  /**
   * {@inheritdoc}
   */
  public function testAddField(): void {
    $this->markTestSkipped('MongoDB does not support partial unique key indexes.');
  }

  /**
   * {@inheritdoc}
   */
  public function testChangeField(): void {
    $this->markTestSkipped('MongoDB does not support partial unique key indexes.');
  }

}
