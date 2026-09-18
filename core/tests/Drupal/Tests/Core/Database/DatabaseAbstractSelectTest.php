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
class DatabaseAbstractSelectTest extends UnitTestCase {

  /**
   * Tests serialization.
   */
  public function testSerialization(): void {
    $abstractQuery = new Select('test');
    $abstractQuery->addColumn('test', 'name');
    $abstractQuery->addColumn('test', 'age', 'age');
    $abstractQuery = $abstractQuery->countQuery();

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
    $this->expectExceptionMessageIs('Drupal\Core\Database\Query\DatabaseAbstract\Select objects containing alter metadatada cannot be serialized');

    $abstractQuery = new Select('test');
    $abstractQuery->addColumn('test', 'name');
    $abstractQuery->addColumn('test', 'age', 'age');
    $abstractQuery->addTag('foo');
    $abstractQuery->addMetaData('bar', new \StdClass());
    $abstractQuery = $abstractQuery->countQuery();

    $serialized = serialize($abstractQuery);
  }

}
