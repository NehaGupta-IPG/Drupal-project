<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\ConnectionNotDefinedException;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
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
   * Tests the compact representation of values that are not scalars.
   */
  public function testToCompactStringWithNonScalarValues(): void {
    $stream = fopen('php://memory', 'r');
    $closed = fopen('php://memory', 'r');
    fclose($closed);
    $recursive = [];
    $recursive[] = &$recursive;
    $info = new SqlExecutionInfo('SELECT 1', [
      ':stream' => $stream,
      ':closed' => $closed,
      ':object' => new \stdClass(),
      ':recursive' => $recursive,
      ':long' => range(1, 21),
    ]);
    $this->assertSame("SELECT 1; [:stream=resource (stream), :closed=resource (closed), :object=object (stdClass), :recursive=[[[[...]]]], :long=[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, ...]]", $info->toCompactString());
    fclose($stream);
  }

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
        'SELECT [name] FROM {test} WHERE [age] > :a; [:a=18]',
        'SELECT [name] FROM {test} WHERE [age] > :a',
        [':a' => 18],
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
   * Tests appending to and stripping from an exception message.
   */
  public function testAppendToAndStripFromMessage(): void {
    $info = new SqlExecutionInfo('SELECT 1', [':a' => 'b'], ['fetch' => 'assoc']);
    $appended = $info->appendToMessage('Failed');
    // The options are not included in the message.
    $this->assertSame("Failed: SELECT 1; [:a='b']", $appended);
    $this->assertSame('Failed', $info->stripFromMessage($appended));

    // Messages without this suffix are returned unchanged.
    $this->assertSame('Failed', $info->stripFromMessage('Failed'));
    $other = (new SqlExecutionInfo('SELECT 2'))->appendToMessage('Failed');
    $this->assertSame($other, $info->stripFromMessage($other));
  }

  /**
   * Tests finding the execution information in a chain of throwables.
   */
  #[DataProvider('providerFromThrowable')]
  public function testFromThrowable(?string $expected, \Throwable $throwable): void {
    $this->assertSame($expected, SqlExecutionInfo::fromThrowable($throwable)?->sqlString);
  }

  /**
   * Provides test cases for ::testFromThrowable().
   */
  public static function providerFromThrowable(): array {
    $carrying = new DatabaseExceptionWrapper('Failed', 0, NULL, new SqlExecutionInfo('SELECT 1'));

    return [
      'carried directly' => ['SELECT 1', $carrying],
      // This is how most query failures surface.
      'wrapped by the entity storage' => [
        'SELECT 1',
        new EntityStorageException('Failed', 0, $carrying),
      ],
      // Not every database exception extends DatabaseExceptionWrapper, so the
      // walk cannot stop at the first one it meets.
      'behind a database exception without the property' => [
        'SELECT 1',
        new ConnectionNotDefinedException('Not defined', 0, $carrying),
      ],
      // The outermost statement is the one the caller ran.
      'outermost of two' => [
        'SELECT 2',
        new DatabaseExceptionWrapper('Outer', 0, $carrying, new SqlExecutionInfo('SELECT 2')),
      ],
      // Thrown by Drupal rather than by a driver.
      'database exception carrying nothing' => [
        NULL,
        new SchemaTableAlreadyExistsException('Table exists'),
      ],
      'no database exception in the chain' => [
        NULL,
        new \RuntimeException('Outer', 0, new \LogicException('Inner')),
      ],
    ];
  }

}
