<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Query\DatabaseAbstract\Select;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Database Abstract Select objects.
 */
#[Group('Database')]
#[Group('DatabaseAbstractSelect')]
class DatabaseAbstractSelectTest extends UnitTestCase {

  /**
   * Tests serialization.
   */
  public function testSerialization(): void {
    $abstractQuery = Select::build('test')
      ->column('test', 'name')
      ->column('test', 'age', 'age')
      ->countQuery();

    $serialized = serialize($abstractQuery);
    $this->assertIsString($serialized);

    $unserialized = unserialize($serialized);
    $this->assertEquals($abstractQuery, $unserialized);
  }

  /**
   * Tests serialization fails if 'alterMetadata' or 'alterTags' are defined.
   */
  public function testSerializationFailsWithAlterData(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessageIs('Drupal\Core\Database\Query\DatabaseAbstract\Select objects containing alter metadata cannot be serialized');

    $abstractQuery = Select::build('test')
      ->column('test', 'name')
      ->column('test', 'age', 'age')
      ->addTag('foo')
      ->addMetaData('bar', new \stdClass())
      ->countQuery();

    serialize($abstractQuery);
  }

}
