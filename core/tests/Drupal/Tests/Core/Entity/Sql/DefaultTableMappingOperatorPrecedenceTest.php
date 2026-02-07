<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity\Sql;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests operator precedence in DefaultTableMapping table name construction.
 */
#[CoversClass(DefaultTableMapping::class)]
#[Group('Entity')]
class DefaultTableMappingOperatorPrecedenceTest extends UnitTestCase {

  /**
   * Tests that revision table name is correctly constructed when NULL.
   *
   * This test demonstrates a bug where operator precedence causes
   * malformed table names when entity type methods return NULL.
   *
   * The bug is in DefaultTableMapping::__construct():
   * @code
   * $this->revisionTable = $this->prefix . $entity_type->getRevisionTable() ?: $entity_type->id() . '_revision';
   * @endcode
   *
   * Due to PHP operator precedence (. binds tighter than ?:), this evaluates as:
   * @code
   * $this->revisionTable = ($this->prefix . NULL) ?: 'entity_id_revision';
   * @endcode
   *
   * When prefix is "test123" and getRevisionTable() returns NULL:
   * - "test123" . NULL = "test123" (truthy)
   * - Fallback is NOT used
   * - Result: "test123" instead of "test123entity_id_revision"
   */
  public function testRevisionTableWithNullValue(): void {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('id')->willReturn('test_entity');
    $entity_type->method('getBaseTable')->willReturn('test_entity');
    $entity_type->method('isRevisionable')->willReturn(TRUE);
    $entity_type->method('isTranslatable')->willReturn(FALSE);
    $entity_type->method('getRevisionTable')->willReturn(NULL);

    $this->assertEquals(
      'simpletest123test_entity_revision',
      (new DefaultTableMapping($entity_type, [], 'simpletest123'))->getRevisionTable()
    );
  }

  /**
   * Tests that revision table name is correctly constructed with empty string.
   */
  public function testRevisionTableWithEmptyString(): void {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('id')->willReturn('test_entity');
    $entity_type->method('getBaseTable')->willReturn('test_entity');
    $entity_type->method('isRevisionable')->willReturn(TRUE);
    $entity_type->method('isTranslatable')->willReturn(FALSE);
    $entity_type->method('getRevisionTable')->willReturn('');

    $this->assertEquals(
      'simpletest456test_entity_revision',
      (new DefaultTableMapping($entity_type, [], 'simpletest456'))->getRevisionTable()
    );
  }

  /**
   * Tests that data table name is correctly constructed when NULL.
   */
  public function testDataTableWithNullValue(): void {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('id')->willReturn('test_entity');
    $entity_type->method('getBaseTable')->willReturn('test_entity');
    $entity_type->method('isRevisionable')->willReturn(FALSE);
    $entity_type->method('isTranslatable')->willReturn(TRUE);
    $entity_type->method('getDataTable')->willReturn(NULL);

    $this->assertEquals(
      'simpletest789test_entity_field_data',
      (new DefaultTableMapping($entity_type, [], 'simpletest789'))->getDataTable()
    );
  }

  /**
   * Tests that base table name is correctly constructed when NULL.
   */
  public function testBaseTableWithNullValue(): void {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('id')->willReturn('test_entity');
    $entity_type->method('getBaseTable')->willReturn(NULL);
    $entity_type->method('isRevisionable')->willReturn(FALSE);
    $entity_type->method('isTranslatable')->willReturn(FALSE);

    $this->assertEquals(
      'simpletest000test_entity',
      (new DefaultTableMapping($entity_type, [], 'simpletest000'))->getBaseTable()
    );
  }

  /**
   * Tests that revision data table is correctly constructed when NULL.
   */
  public function testRevisionDataTableWithNullValue(): void {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('id')->willReturn('test_entity');
    $entity_type->method('getBaseTable')->willReturn('test_entity');
    $entity_type->method('isRevisionable')->willReturn(TRUE);
    $entity_type->method('isTranslatable')->willReturn(TRUE);
    $entity_type->method('getRevisionTable')->willReturn('test_entity_revision');
    $entity_type->method('getDataTable')->willReturn('test_entity_field_data');
    $entity_type->method('getRevisionDataTable')->willReturn(NULL);

    $this->assertEquals(
      'simpletest111test_entity_field_revision',
      (new DefaultTableMapping($entity_type, [], 'simpletest111'))->getRevisionDataTable()
    );
  }

}
