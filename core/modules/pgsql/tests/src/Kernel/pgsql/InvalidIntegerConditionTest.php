<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use Drupal\pgsql\Driver\Database\pgsql\Condition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests invalid values in conditions on PostgreSQL integer fields.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class InvalidIntegerConditionTest extends DriverSpecificDatabaseTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->connection->schema()->createTable('pgsql_integer_condition', [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'not null' => TRUE,
        ],
        'integer_value' => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'bigint_value' => [
          'type' => 'int',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'unsigned_value' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
    ]);

    $this->connection->insert('pgsql_integer_condition')
      ->fields(['integer_value', 'bigint_value', 'unsigned_value'])
      ->values([1, 2147483648, 2147483648])
      ->values([2, 2, 2])
      ->execute();
  }

  /**
   * Tests that PostgreSQL supplies its database-specific Condition class.
   */
  public function testConditionClass(): void {
    $this->assertInstanceOf(Condition::class, $this->connection->condition('AND'));
  }

  /**
   * Tests malformed integer values in IN conditions.
   */
  public function testInvalidIntegerInValues(): void {
    foreach (['test', '1test', '', '1.5', '9223372036854775808'] as $value) {
      $query = $this->connection->select('pgsql_integer_condition', 't');
      $query->addField('t', 'id');
      $query->condition('t.integer_value', [$value], 'IN');
      $this->assertCount(0, $query->execute()->fetchAll(), "Value '$value' does not cause a PostgreSQL integer conversion error.");
    }

    // A value outside an integer column's range is also impossible to match.
    $query = $this->connection->select('pgsql_integer_condition', 't');
    $query->addField('t', 'id');
    $query->condition('t.integer_value', ['2147483648'], 'IN');
    $this->assertCount(0, $query->execute()->fetchAll());
  }

  /**
   * Tests that native PostgreSQL field types determine accepted values.
   */
  public function testDriverSpecificIntegerRanges(): void {
    // The same value that overflows an integer field is valid in bigint.
    $query = $this->connection->select('pgsql_integer_condition', 't');
    $query->addField('t', 'id');
    $query->condition('t.bigint_value', ['2147483648'], 'IN');
    $this->assertCount(1, $query->execute()->fetchAll());

    // PostgreSQL widens an unsigned normal Drupal int to bigint, so this value
    // is also valid even though the Schema API size is still "normal".
    $query = $this->connection->select('pgsql_integer_condition', 't');
    $query->addField('t', 'id');
    $query->condition('t.unsigned_value', ['2147483648'], 'IN');
    $this->assertCount(1, $query->execute()->fetchAll());
  }

  /**
   * Tests mixed valid and invalid IN values.
   */
  public function testMixedIntegerInValues(): void {
    $query = $this->connection->select('pgsql_integer_condition', 't');
    $query->addField('t', 'id');
    $query->condition('t.integer_value', ['test', '1', '2147483648'], 'IN');
    $this->assertCount(1, $query->execute()->fetchAll());
  }

}
