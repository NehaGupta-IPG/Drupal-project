<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql\Plugin\views;

use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\views\Plugin\views\query\CastSqlInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests PostgreSQL specific cast handling.
 *
 * @see \Drupal\pgsql\Plugin\views\query\PgsqlCastSql
 */
#[Group('Database')]
#[Group('views')]
#[RunTestsInSeparateProcesses]
class PgsqlCastSqlTest extends DriverSpecificKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views'];

  /**
   * The cast handling of the database.
   */
  protected CastSqlInterface $castSql;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->castSql = $this->container->get('views.cast_sql');

    // A table with an integer ID, like most entity types have.
    $this->connection->schema()->createTable('test_integer_id', [
      'fields' => [
        'id' => ['type' => 'int', 'not null' => TRUE],
        'name' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
    ]);
    // A table that stores the IDs it tracks as strings, like {file_usage}.
    $this->connection->schema()->createTable('test_string_id', [
      'fields' => [
        'id' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => TRUE],
        'label' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
    ]);

    $this->connection->insert('test_integer_id')
      ->fields(['id', 'name'])
      ->values(['id' => 1, 'name' => 'first'])
      ->values(['id' => 2, 'name' => 'second'])
      ->execute();
    $this->connection->insert('test_string_id')
      ->fields(['id', 'label'])
      ->values(['id' => '1', 'label' => 'tracks the first'])
      ->values(['id' => 'a_string_id', 'label' => 'tracks a string ID'])
      ->execute();
  }

  /**
   * Tests that columns of the same type are compared as they are.
   */
  public function testMatchingColumnTypes(): void {
    $operands = $this->castSql->getComparableOperands(
      ['expression' => 'a.id', 'table' => 'test_integer_id', 'column' => 'id'],
      ['expression' => 'b.id', 'table' => 'test_integer_id', 'column' => 'id'],
    );
    $this->assertSame(['a.id', 'b.id'], $operands);

    $operands = $this->castSql->getComparableOperands(
      ['expression' => 'a.id', 'table' => 'test_string_id', 'column' => 'id'],
      ['expression' => 'b.label', 'table' => 'test_string_id', 'column' => 'label'],
    );
    $this->assertSame(['a.id', 'b.label'], $operands);
  }

  /**
   * Tests that only the string operand of a comparison is cast.
   */
  public function testMismatchingColumnTypes(): void {
    $integer = ['expression' => 'a.id', 'table' => 'test_integer_id', 'column' => 'id'];
    $string = ['expression' => 'b.id', 'table' => 'test_string_id', 'column' => 'id'];

    [$left, $right] = $this->castSql->getComparableOperands($integer, $string);
    $this->assertSame('a.id', $left);
    $this->assertSame($this->castSql->getFieldAsInt('b.id'), $right);

    [$left, $right] = $this->castSql->getComparableOperands($string, $integer);
    $this->assertSame($this->castSql->getFieldAsInt('b.id'), $left);
    $this->assertSame('a.id', $right);
  }

  /**
   * Tests that operands of an unknown column are compared as they are.
   */
  public function testUnknownColumns(): void {
    $operands = $this->castSql->getComparableOperands(
      ['expression' => 'a.id', 'table' => 'test_integer_id', 'column' => 'no_such_column'],
      ['expression' => 'b.id', 'table' => 'no_such_table', 'column' => 'id'],
    );
    $this->assertSame(['a.id', 'b.id'], $operands);

    // Joins can use a formula instead of a column, which has no type to look up.
    $operands = $this->castSql->getComparableOperands(
      ['expression' => '(SELECT MAX(id) FROM {test_integer_id})', 'table' => NULL, 'column' => NULL],
      ['expression' => 'b.id', 'table' => 'test_string_id', 'column' => 'id'],
    );
    $this->assertSame(['(SELECT MAX(id) FROM {test_integer_id})', 'b.id'], $operands);
  }

  /**
   * Tests that a string that is not a number does not fail the query.
   */
  public function testStringIdIsNotANumber(): void {
    [$left, $right] = $this->castSql->getComparableOperands(
      ['expression' => 'test_integer_id.id', 'table' => 'test_integer_id', 'column' => 'id'],
      ['expression' => 'test_string_id.id', 'table' => 'test_string_id', 'column' => 'id'],
    );

    $query = $this->connection->select('test_integer_id');
    $query->innerJoin('test_string_id', 'test_string_id', "$left = $right");
    $query->addField('test_integer_id', 'name');
    $query->addField('test_string_id', 'label');

    $this->assertSame(['first' => 'tracks the first'], $query->execute()->fetchAllKeyed());
  }

}
