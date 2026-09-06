<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\VersionedSchema;
use Drupal\Core\Database\Exception\SchemaDefinitionException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests VersionedSchema definition object validations.
 */
#[Group('Database')]
#[CoversClass(VersionedSchema::class)]
class VersionedSchemaDefinitionTest extends UnitTestCase {

  /**
   * Basic test of the object methods.
   */
  public function testVersionedSchemaMethods(): void {
    $schema0 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 0,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::int(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schema1 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 11,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::serial(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $versionedSchema = new VersionedSchema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      schemaVersions: [
        $schema1,
        $schema0,
      ],
    );

    // Check ::schemaVersions().
    $this->assertSame([0, 11], $versionedSchema->schemaVersions());

    // Check ::getSchemaVersionDefinition().
    $schemaDefinition = $versionedSchema->getSchemaVersionDefinition(0);
    $this->assertInstanceOf(Schema::class, $schemaDefinition);
    $this->assertSame('foo', $schemaDefinition->name);
    $this->assertSame(0, $schemaDefinition->version);

    // Check ::getLatestSchemaVersionDefinition().
    $schemaDefinition = $versionedSchema->getLatestSchemaVersionDefinition();
    $this->assertInstanceOf(Schema::class, $schemaDefinition);
    $this->assertSame('foo', $schemaDefinition->name);
    $this->assertSame(11, $schemaDefinition->version);
  }

  /**
   * Tests passing a non Schema array member to ::$schemaVersions.
   */
  public function testInvalidSchema(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessageIs('All members of the \'schemaVersions\' argument of the \'foo\' VersionedSchema must be Schema objects');
    $schema0 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 0,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::int(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schema1 = 'bar';

    new VersionedSchema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      // @phpstan-ignore argument.type
      schemaVersions: [
        $schema1,
        $schema0,
      ],
    );
  }

  /**
   * Tests passing a mismatching Schema type to ::$schemaVersions.
   */
  public function testMismatchingSchemaType(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessageIs('A schema does not match the \'test\' type of the \'foo\' VersionedSchema');
    $schema0 = new Schema(
      type: SchemaDefinitionType::Module,
      name: 'foo',
      version: 0,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::int(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schema1 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 11,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::serial(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    new VersionedSchema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      schemaVersions: [
        $schema1,
        $schema0,
      ],
    );
  }

  /**
   * Tests passing a mismatching Schema name to ::$schemaVersions.
   */
  public function testMismatchingSchemaName(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessageIs('The \'bar\' schema does not match the name of the \'foo\' VersionedSchema');
    $schema0 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'bar',
      version: 0,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::int(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schema1 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 11,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::serial(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    new VersionedSchema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      schemaVersions: [
        $schema1,
        $schema0,
      ],
    );
  }

  /**
   * Tests passing a Schema missing the version level.
   */
  public function testMissingSchemaVersion(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessageIs('At least one of the schema versions of \'foo\' does not indicate the version level');
    $schema0 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 0,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::int(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schema1 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::serial(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    new VersionedSchema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      schemaVersions: [
        $schema1,
        $schema0,
      ],
    );
  }

  /**
   * Tests passing two Schema with the same version level.
   */
  public function testDuplicateSchemaVersion(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessageIs('Duplicate versions for \'foo\' VersionedSchema: 5, 5, 10');
    $schema0 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 5,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::int(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schema1 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 10,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::serial(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schema2 = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      version: 5,
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::serial(
              name: 'id',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    new VersionedSchema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      schemaVersions: [
        $schema1,
        $schema0,
        $schema2,
      ],
    );
  }

}
