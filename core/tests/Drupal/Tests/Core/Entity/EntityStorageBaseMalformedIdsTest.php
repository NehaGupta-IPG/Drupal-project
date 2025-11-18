<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Tests EntityStorageBase with malformed IDs.
 */
#[CoversClass(EntityStorageBase::class)]
#[Group('Entity')]
class EntityStorageBaseMalformedIdsTest extends UnitTestCase {

  /**
   * Data provider for malformed IDs.
   */
  public static function providerMalformedIds(): \Generator {
    yield 'nested-array' => [['x' => ['x']]];
    yield 'object-value' => [[(object) ['id' => 1]]];
    yield 'boolean-value' => [[TRUE, FALSE]];
    yield 'mixed-valid-and-nested' => [[1, 2, ['nested'], 3]];
    yield 'associative-with-nested' => [[0 => 1, 1 => ['bad'], 2 => 3]];
  }

  /**
   * Test loadMultiple with malformed IDs.
   *
   * @legacy-covers ::loadMultiple
   */
  #[DataProvider('providerMalformedIds')]
  public function testLoadMultipleWithMalformedIds(array $ids): void {
    $mock_base = $this->getMockBuilder(StubEntityStorageBase::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['doLoadMultiple', 'postLoad'])
      ->getMock();

    $mock_entity_type = $this->createMock(EntityTypeInterface::class);
    $mock_entity_type->method('isStaticallyCacheable')->willReturn(FALSE);

    $ref_entity_type = new \ReflectionProperty($mock_base, 'entityType');
    $ref_entity_type->setValue($mock_base, $mock_entity_type);

    $result = $mock_base->loadMultiple($ids);
    $this->assertIsArray($result);
  }

  /**
   * Data provider for valid IDs.
   */
  public static function providerValidIds(): \Generator {
    yield 'numeric-ids' => [[1, 2, 3]];
    yield 'string-ids' => [['1', '2', '3']];
    yield 'associative-array' => [[0 => 1, 1 => 2, 2 => 3]];
  }

  /**
   * Test loadMultiple with valid IDs.
   *
   * @legacy-covers ::loadMultiple
   */
  #[DataProvider('providerValidIds')]
  public function testLoadMultipleWithValidIds(array $ids): void {
    $mock_base = $this->getMockBuilder(StubEntityStorageBase::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['doLoadMultiple', 'postLoad'])
      ->getMock();

    $mock_entity_type = $this->createMock(EntityTypeInterface::class);
    $mock_entity_type->method('isStaticallyCacheable')->willReturn(FALSE);

    $ref_entity_type = new \ReflectionProperty($mock_base, 'entityType');
    $ref_entity_type->setValue($mock_base, $mock_entity_type);

    $result = $mock_base->loadMultiple($ids);
    $this->assertIsArray($result);
  }

}
