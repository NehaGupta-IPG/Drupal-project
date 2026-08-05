<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Tests\Core\Database\Stub\StubSchema;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the abstract Schema class.
 */
#[CoversClass(Schema::class)]
#[Group('Database')]
class SchemaTest extends UnitTestCase {

  /**
   * Tests creating a specialized index on a driver not implementing any.
   */
  public function testCreateSpecializedIndexNotImplemented(): void {
    $schema = new StubSchema($this->createStub(Connection::class));
    $index = new Index(name: 'ages', columns: ['age']);
    $this->expectException(\BadMethodCallException::class);
    $this->expectExceptionMessage(StubSchema::class . '::createSpecializedIndex() not implemented.');
    (new \ReflectionMethod($schema, 'createSpecializedIndex'))->invoke($schema, 'test_table', $index);
  }

}
