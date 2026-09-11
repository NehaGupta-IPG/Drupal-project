<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\ConnectionNotDefinedException;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Database\SqlExecutionInfo.
 */
#[CoversClass(SqlExecutionInfo::class)]
#[Group('Database')]
class SqlExecutionInfoTest extends UnitTestCase {

  /**
   * Tests the compact representation used in exception messages.
   */
  #[DataProvider('providerToCompactString')]
  public function testToCompactString(string $expected, string $sql, array $placeholders): void {
    $this->assertSame($expected, (new SqlExecutionInfo($sql, $placeholders))->toCompactString());
  }

  /**
   * Provides test cases for ::testToCompactString().
   */
  public static function providerToCompactString(): array {
    return [
      'no placeholders' => [
        'SELECT 1; []',
        'SELECT 1',
        [],
      ],
      'named placeholders' => [
        "SELECT [name] FROM {test} WHERE [age] > :db_condition_placeholder_0; [:db_condition_placeholder_0=18]",
        'SELECT [name] FROM {test} WHERE [age] > :db_condition_placeholder_0',
        [':db_condition_placeholder_0' => 18],
      ],
      'positional placeholders' => [
        "SELECT 1; [0='a', 1='b']",
        'SELECT 1',
        ['a', 'b'],
      ],
      'value types' => [
        "SELECT 1; [:a=NULL, :b=TRUE, :c=FALSE, :d=1.5, :e='', :f=['x', 2]]",
        'SELECT 1',
        [
          ':a' => NULL,
          ':b' => TRUE,
          ':c' => FALSE,
          ':d' => 1.5,
          ':e' => '',
          ':f' => ['x', 2],
        ],
      ],
      'multiline statement is collapsed' => [
        "SELECT 1 FROM {test} WHERE [a] = :a; [:a='b']",
        "  SELECT 1\nFROM {test}\n  WHERE [a] = :a  ",
        [':a' => 'b'],
      ],
      'multiline value stays on one line' => [
        "SELECT 1; [:a='first\\nsecond']",
        'SELECT 1',
        [':a' => "first\nsecond"],
      ],
    ];
  }

  /**
   * Tests that appending to and stripping from a message round-trips.
   */
  public function testAppendAndStripRoundTrip(): void {
    $info = new SqlExecutionInfo(
      'SELECT [name] FROM {test} WHERE [name] = :db_condition_placeholder_0',
      [':db_condition_placeholder_0' => "a: weird; value\nover lines"],
      ['fetch' => 'assoc'],
    );
    $message = 'SQLSTATE[42S22]: Column not found: 1054 Unknown column';

    $appended = $info->appendToMessage($message);

    $this->assertStringStartsWith($message . ': ', $appended);
    $this->assertStringNotContainsString("\n", $appended);
    // The options are only exposed by the verbose dump.
    $this->assertStringNotContainsString('assoc', $appended);
    $this->assertSame($message, $info->stripFromMessage($appended));
  }

  /**
   * Tests finding the execution information in a chain of throwables.
   *
   * @param bool $expected
   *   Whether the chain is expected to yield the information.
   * @param \Throwable $throwable
   *   The head of the chain to inspect.
   */
  #[DataProvider('providerFromThrowable')]
  public function testFromThrowable(bool $expected, \Throwable $throwable): void {
    $info = SqlExecutionInfo::fromThrowable($throwable);

    if (!$expected) {
      $this->assertNull($info);
      return;
    }
    $this->assertInstanceOf(SqlExecutionInfo::class, $info);
    $this->assertSame('SELECT 1', $info->sqlString);
  }

  /**
   * Provides test cases for ::testFromThrowable().
   */
  public static function providerFromThrowable(): array {
    $info = new SqlExecutionInfo('SELECT 1');
    $carrying = new DatabaseExceptionWrapper('Failed', 0, NULL, $info);

    return [
      'carried directly' => [TRUE, $carrying],
      // This is how most query failures surface.
      'wrapped by the entity storage' => [
        TRUE,
        new EntityStorageException($carrying->getMessage(), 0, $carrying),
      ],
      'wrapped twice' => [
        TRUE,
        new \RuntimeException('Outer', 0, new \LogicException('Middle', 0, $carrying)),
      ],
      // Not every database exception extends DatabaseExceptionBase, so the
      // walk cannot stop at the first one it meets.
      'behind a database exception without the property' => [
        TRUE,
        new ConnectionNotDefinedException('Not defined', 0, $carrying),
      ],
      // Thrown by Drupal rather than by a driver.
      'database exception carrying nothing' => [
        FALSE,
        new SchemaObjectExistsException('Table exists'),
      ],
      'no database exception in the chain' => [
        FALSE,
        new \RuntimeException('Outer', 0, new \LogicException('Inner')),
      ],
      'nothing to walk' => [FALSE, new \RuntimeException('Alone')],
    ];
  }

  /**
   * Tests that the first information in the chain is the one returned.
   *
   * The outermost statement is the one the caller ran, so it is the one that
   * says what failed.
   */
  public function testFromThrowableReturnsTheOutermostInformation(): void {
    $inner = new DatabaseExceptionWrapper('Inner', 0, NULL, new SqlExecutionInfo('SELECT 2'));
    $outer = new DatabaseExceptionWrapper('Outer', 0, $inner, new SqlExecutionInfo('SELECT 1'));

    $this->assertSame('SELECT 1', SqlExecutionInfo::fromThrowable($outer)->sqlString);
    $this->assertSame('SELECT 2', SqlExecutionInfo::fromThrowable($inner)->sqlString);
  }

  /**
   * Tests that stripping leaves a message without the suffix untouched.
   */
  public function testStripFromMessageWithoutSuffix(): void {
    $info = new SqlExecutionInfo('SELECT 1');

    $this->assertSame('No suffix here', $info->stripFromMessage('No suffix here'));
    // A suffix built from different execution information is not removed.
    $other = (new SqlExecutionInfo('SELECT 2'))->appendToMessage('Message');
    $this->assertSame($other, $info->stripFromMessage($other));
  }

}
