<?php

declare(strict_types=1);

namespace Drupal\Tests\sqlite\Kernel\sqlite;

use Drupal\KernelTests\Core\Database\DriverSpecificSchemaTestBase;

/**
 * Tests schema API for the SQLite driver.
 *
 * @group Database
 */
class SchemaTest extends DriverSpecificSchemaTestBase {

  /**
   * Sets up the database schema for testing.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createTestTable();
  }

  /**
   * Creates a test table with a VARCHAR length constraint.
   */
  protected function createTestTable(): void {
    $schema = $this->schema->createTable('test_table', [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'not null' => TRUE,
        ],
        'name' => [
          'type' => 'varchar',
          // Should enforce a max length of 10 characters.
          'length' => 10,
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function checkSchemaComment(string|false $description, string $table, ?string $column = NULL): void {
    // The sqlite driver schema does not support fetching table/column
    // comments.
  }

  /**
   * {@inheritdoc}
   */
  protected function tryInsertExpectsIntegrityConstraintViolationException(string $tableName): void {
    // Sqlite does not throw an IntegrityConstraintViolationException here.
  }

  /**
   * {@inheritdoc}
   */
  public function testTableWithSpecificDataType(): void {
    $table_specification = [
      'description' => 'Schema table description.',
      'fields' => [
        'timestamp'  => [
          'sqlite_type' => 'datetime',
          'not null' => FALSE,
          'default' => NULL,
        ],
      ],
    ];
    $this->schema->createTable('test_timestamp', $table_specification);
    $this->assertTrue($this->schema->tableExists('test_timestamp'));
  }

  /**
   * {@inheritdoc}
   */
  public function testTableInsertWithLengthConstraint(): void {
    // Valid insert (should pass).
    $this->schema->insert('test_table')
      ->fields(['name'])
      ->values(['short'])
      ->execute();
    $this->assertEquals(1, $this->schema->select('test_table')->countQuery()->execute()->fetchField());

    // Invalid insert (should fail).
    try {
      $this->schema->insert('test_table')
        ->fields(['name'])
        ->values(['this_is_too_long'])
        ->execute();
      $this->fail('Expected an exception for exceeding length constraint.');
    }
    catch (\Exception $e) {
      $this->assertStringContainsString('CHECK constraint failed', $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function testTableUpdateWithLengthConstraint(): void {
    // Insert a valid row.
    $id = $this->schema->insert('test_table')
      ->fields(['name'])
      ->values(['validname'])
      ->execute();

    // Valid update (should pass).
    $this->schema->update('test_table')
      ->fields(['name' => 'newvalue'])
      ->condition('id', $id)
      ->execute();
    $this->assertEquals('newvalue', $this->schema->select('test_table')
      ->fields('test_table', ['name'])
      ->condition('id', $id)
      ->execute()
      ->fetchField());

    // Invalid update (should fail).
    try {
      $this->schema->update('test_table')
        ->fields(['name' => 'this_is_too_long'])
        ->condition('id', $id)
        ->execute();
      $this->fail('Expected an exception for exceeding length constraint.');
    }
    catch (\Exception $e) {
      $this->assertStringContainsString('CHECK constraint failed', $e->getMessage());
    }
  }

  /**
   * @covers \Drupal\sqlite\Driver\Database\sqlite\Schema::introspectIndexSchema
   */
  public function testIntrospectIndexSchema(): void {
    $table_specification = [
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        'test_field_1'  => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        'test_field_2'  => [
          'type' => 'int',
          'default' => 0,
        ],
        'test_field_3'  => [
          'type' => 'int',
          'default' => 0,
        ],
        'test_field_4'  => [
          'type' => 'int',
          'default' => 0,
        ],
        'test_field_5'  => [
          'type' => 'int',
          'default' => 0,
        ],
      ],
      'primary key' => ['id', 'test_field_1'],
      'unique keys' => [
        'test_field_2' => ['test_field_2'],
        'test_field_3_test_field_4' => ['test_field_3', 'test_field_4'],
      ],
      'indexes' => [
        'test_field_4' => ['test_field_4'],
        'test_field_4_test_field_5' => ['test_field_4', 'test_field_5'],
      ],
    ];

    $table_name = strtolower($this->getRandomGenerator()->name());
    $this->schema->createTable($table_name, $table_specification);

    unset($table_specification['fields']);

    $introspect_index_schema = new \ReflectionMethod(get_class($this->schema), 'introspectIndexSchema');
    $index_schema = $introspect_index_schema->invoke($this->schema, $table_name);

    $this->assertEquals($table_specification, $index_schema);
  }

}
