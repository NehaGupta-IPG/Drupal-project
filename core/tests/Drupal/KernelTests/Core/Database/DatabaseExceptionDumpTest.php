<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\ConnectionNotDefinedException;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\TransactionOutOfOrderException;
use Drupal\Core\Database\Utility\SqlDumper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests dumping the statement of every kind of failing database operation.
 *
 * The error handlers dump the SQL execution information of any database
 * exception that reaches them, so every kind of database failure has to be
 * dumpable, or produce no dump at all. This walks the kinds that every driver
 * can produce; the ones a driver recognises by its own error codes are covered
 * per driver.
 *
 * @see \Drupal\Tests\mysql\Kernel\mysql\DatabaseExceptionDumpTest
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class DatabaseExceptionDumpTest extends DatabaseTestBase {

  use DatabaseExceptionDumpAssertTrait;

  /**
   * A payload that must never reach a page unescaped.
   *
   * It also carries Symfony console markup: a registered tag the console
   * output formatter would eat, and a color it would throw on.
   */
  const MARKUP_PAYLOAD = "<script>alert('xss')</script> <error>eaten?</error> <fg=nosuchcolor>invalid</>";

  /**
   * The kinds of database failure any driver can produce.
   *
   * @return array<string, array{0: string, 1: class-string<\Throwable>, 2: bool}>
   *   Each case is the name of the method provoking the failure, the exception
   *   class it is expected to throw, and whether that exception is expected to
   *   carry SQL execution information.
   */
  public static function providerExceptionKinds(): array {
    return [
      'unknown table' => ['unknownTable', DatabaseExceptionWrapper::class, TRUE],
      'unknown column' => ['unknownColumn', DatabaseExceptionWrapper::class, TRUE],
      'syntax error' => ['syntaxError', DatabaseExceptionWrapper::class, TRUE],
      'array placeholders' => ['arrayPlaceholders', DatabaseExceptionWrapper::class, TRUE],
      'multi-join query' => ['longQuery', DatabaseExceptionWrapper::class, TRUE],
      'like condition' => ['likeCondition', DatabaseExceptionWrapper::class, TRUE],
      'duplicate key' => ['duplicateKey', IntegrityConstraintViolationException::class, TRUE],
      'not null violation' => ['notNull', IntegrityConstraintViolationException::class, TRUE],
      'markup in the values' => ['markupInValues', IntegrityConstraintViolationException::class, TRUE],
      // Thrown by Drupal instead of by a driver, so there is no statement to
      // report.
      'table exists' => ['tableExists', SchemaObjectExistsException::class, FALSE],
      'table missing' => ['tableMissing', SchemaObjectDoesNotExistException::class, FALSE],
      'transaction out of order' => ['transactionOutOfOrder', TransactionOutOfOrderException::class, FALSE],
      // This one does not even extend DatabaseExceptionBase, so it has no
      // $info property at all. Reading one unconditionally used to be fatal.
      'connection not defined' => ['connectionNotDefined', ConnectionNotDefinedException::class, FALSE],
    ];
  }

  /**
   * Tests that every kind of database failure is reported and dumpable.
   *
   * @param string $method
   *   The method provoking the failure.
   * @param string $expected_class
   *   The exception class the failure is expected to throw.
   * @param bool $carries_info
   *   Whether the exception is expected to carry SQL execution information.
   */
  #[DataProvider('providerExceptionKinds')]
  public function testExceptionKindIsDumpable(string $method, string $expected_class, bool $carries_info): void {
    try {
      $this->{$method}();
      $this->fail("The '$method' case did not throw.");
    }
    catch (DatabaseException $e) {
      $this->assertInstanceOf($expected_class, $e);
      if ($carries_info) {
        $this->assertDump($e);
      }
      else {
        $this->assertNoDump($e);
      }
    }
  }

  /**
   * Tests that a statement without placeholders is dumped without a section.
   */
  public function testStatementWithoutPlaceholders(): void {
    try {
      $this->unknownTable();
      $this->fail('The query did not throw.');
    }
    catch (DatabaseExceptionWrapper $e) {
      $info = $this->assertDump($e);
      $this->assertSame([], $info->placeholders);
      // The empty array gets no heading of its own.
      $dump = (new SqlDumper())->export($info, FALSE, FALSE);
      $this->assertStringNotContainsString('Placeholders', $dump);
      // The options a query is run with are always reported.
      $this->assertStringContainsString('Options', $dump);
    }
  }

  /**
   * Tests that an array placeholder is dumped as the values it expanded to.
   */
  public function testArrayPlaceholders(): void {
    try {
      $this->arrayPlaceholders();
      $this->fail('The query did not throw.');
    }
    catch (DatabaseExceptionWrapper $e) {
      $info = $this->assertDump($e);
      // \Drupal\Core\Database\Connection::expandArguments() replaces the
      // trailing brackets with an underscore and appends the index of each
      // value, before the statement is prepared, so that is what the failing
      // statement contains.
      foreach ([':names__0', ':names__1', ':names__2'] as $name) {
        $this->assertStringContainsString($name, $info->sqlString);
      }
      $this->assertStringNotContainsString(':names[]', $info->sqlString);
      // The options of the query are reported as they were passed.
      $this->assertSame(FetchAs::Associative, $info->options['fetch']);
      $dump = (new SqlDumper())->export($info, FALSE, FALSE);
      $this->assertStringContainsString('Options', $dump);
    }
  }

  /**
   * Tests that placeholder values are escaped for a page and raw for a stream.
   *
   * Placeholder values are user input. On a page they have to be escaped,
   * because the caller marks the dump as safe markup. On a terminal there is
   * no markup to escape, and mangling the values would only make them harder
   * to recognise.
   */
  public function testMarkupInPlaceholderValues(): void {
    try {
      $this->markupInValues();
      $this->fail('The insert did not throw.');
    }
    catch (IntegrityConstraintViolationException $e) {
      $info = $this->assertDump($e);
      $this->assertContains(self::MARKUP_PAYLOAD, $info->placeholders);

      $dumper = new SqlDumper();
      // The var dumper emits inline <script> and <style> of its own, so it is
      // the payload that has to be gone, not every tag.
      $html = $dumper->export($info, FALSE, TRUE);
      $this->assertStringNotContainsString(self::MARKUP_PAYLOAD, $html);
      $this->assertStringContainsString('&lt;script&gt;alert(&#039;xss&#039;)', $html);
      $this->assertStringContainsString('&lt;fg=nosuchcolor&gt;', $html);

      $cli = $dumper->export($info, FALSE, FALSE);
      $this->assertStringContainsString(self::MARKUP_PAYLOAD, $cli);
      $this->assertStringNotContainsString('&lt;script&gt;', $cli);
    }
  }

  /**
   * Tests that the dumped statement is reformatted.
   *
   * Reporting a statement as it was executed is what the dump exists to
   * improve on: a query builder puts a whole WHERE clause on one line.
   */
  public function testStatementIsFormatted(): void {
    try {
      $this->longQuery();
      $this->fail('The query did not throw.');
    }
    catch (DatabaseExceptionWrapper $e) {
      $info = $this->assertDump($e);
      // The statement as executed holds the whole WHERE clause on one line.
      $this->assertMatchesRegularExpression('/\nWHERE [^\n]* AND [^\n]* AND /', $info->sqlString);

      $dump = (new SqlDumper())->export($info, FALSE, FALSE);
      // In the dump every condition got its own indented line, and the joined
      // tables are all still there.
      $this->assertStringContainsString("\n  AND (", $dump);
      $this->assertMatchesRegularExpression('/SQL statement.*SELECT.*INNER JOIN.*LEFT.*JOIN.*WHERE.*ORDER BY/s', $dump);
    }
  }

  /**
   * Tests that a LIKE condition is reported as the driver wrote it.
   *
   * Every driver renders this differently: MySQL ends the condition with
   * ESCAPE '\\\\' because it reads a backslash in a single quoted string as an
   * escape character, SQLite with ESCAPE '\\' because it does not, and
   * PostgreSQL rewrites the operator to ILIKE and appends nothing. The dump
   * has to report all three in full.
   *
   * @see \Drupal\Core\Database\Utility\SqlDumper::formatSql()
   */
  public function testLikeConditionIsReportedFaithfully(): void {
    try {
      $this->likeCondition();
      $this->fail('The query did not throw.');
    }
    catch (DatabaseExceptionWrapper $e) {
      $info = $this->assertDump($e);
      $this->assertMatchesRegularExpression('/I?LIKE/', $info->sqlString);

      $dump = (new SqlDumper())->export($info, FALSE, FALSE);
      // The whole statement is reported, whether it was reformatted or left as
      // it stands, and the formatter does not claim it is malformed.
      $this->assertStringContainsString(
        preg_replace('/\s+/', '', $info->sqlString),
        preg_replace('/\s+/', '', $dump),
      );
      $this->assertStringNotContainsString('WARNING', $dump);
    }
  }

  /**
   * Tests that a database exception wrapped by another one is still dumped.
   *
   * This is how most query failures surface: the layer that ran the query
   * catches the database exception and rethrows one of its own, keeping the
   * original as the previous throwable. The wrapper is not a database
   * exception, so the statement has to be looked for along the whole chain.
   *
   * @see \Drupal\KernelTests\Core\Entity\EntityStorageExceptionDumpTest
   */
  public function testWrappedExceptionIsDumped(): void {
    try {
      $this->wrappedInAnotherException();
      $this->fail('The wrapped query did not throw.');
    }
    catch (\RuntimeException $wrapper) {
      $info = $this->assertDump($wrapper);
      $this->assertStringContainsString('palm_height', $info->sqlString);
      // The wrapper copied the message, so it carries the compact statement
      // the dump repeats in full, and stripping it off works on the wrapper.
      $this->assertStringNotContainsString(
        $info->toCompactString(),
        $info->stripFromMessage($wrapper->getMessage()),
      );
      // Wrapping it a second time changes nothing.
      $twice = new \RuntimeException('Something went wrong.', 0, $wrapper);
      $this->assertSame($info, $this->executionInfo($twice));
    }
  }

  /**
   * Runs a failing query and rethrows the failure wrapped in another one.
   */
  protected function wrappedInAnotherException(): void {
    try {
      $this->unknownColumn();
    }
    catch (DatabaseExceptionWrapper $e) {
      // Wrapped the way \Drupal\Core\Entity\Sql\SqlContentEntityStorage does
      // it: message and code copied, the original kept as previous throwable.
      throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Selects from a table that does not exist, without placeholders.
   */
  protected function unknownTable(): void {
    $this->connection->query('SELECT [name] FROM {bananas_are_awesome}');
  }

  /**
   * Selects with a condition on a column that does not exist.
   *
   * The column is qualified with the table alias on purpose. SQLite reads a
   * double quoted identifier that resolves to no column as a string literal
   * instead, and an unqualified condition would then quietly return rows.
   */
  protected function unknownColumn(): void {
    $this->connection->select('test', 't')
      ->fields('t', ['id', 'name'])
      ->condition('t.palm_height', 180, '>')
      ->execute();
  }

  /**
   * Runs a statement that is not valid SQL.
   */
  protected function syntaxError(): void {
    $this->connection->query('SELECT FROM WHERE [id] = :id', [':id' => 1]);
  }

  /**
   * Runs a failing statement with an array placeholder and query options.
   */
  protected function arrayPlaceholders(): void {
    $this->connection->query(
      'SELECT [t].[id] FROM {test} [t] WHERE [t].[name] IN (:names[]) AND [t].[palm_height] > :height',
      [
        ':names[]' => ['John', 'George', 'Ringo'],
        ':height' => 180,
      ],
      ['fetch' => FetchAs::Associative],
    );
  }

  /**
   * Runs a failing query builder statement with joins, ordering and a range.
   */
  protected function longQuery(): void {
    $query = $this->connection->select('test', 't');
    $query->innerJoin('test_task', 'tt', '[t].[id] = [tt].[pid]');
    $query->leftJoin('test_people', 'p', '[t].[name] = [p].[name]');
    $query->fields('t', ['id', 'name', 'age', 'job']);
    $query->fields('tt', ['task', 'priority']);
    $query->condition('tt.priority', 1);
    $query->condition('t.job', ['Singer', 'Drummer'], 'IN');
    // The column this condition is on does not exist.
    $query->condition('p.palm_height', [100, 200], 'BETWEEN');
    $query->orderBy('t.age', 'DESC');
    $query->range(0, 10);
    $query->execute();
  }

  /**
   * Runs a failing query builder statement with a LIKE condition.
   */
  protected function likeCondition(): void {
    $this->connection->select('test', 't')
      ->fields('t', ['id', 'name'])
      ->condition('t.name', '%o%', 'LIKE')
      // The column this condition is on does not exist.
      ->condition('t.palm_height', 180, '>')
      ->orderBy('t.name')
      ->execute();
  }

  /**
   * Inserts a name that is already taken, violating a unique key.
   */
  protected function duplicateKey(): void {
    $this->connection->insert('test')
      ->fields([
        'name' => 'John',
        'age' => 47,
        'job' => 'Singer',
      ])
      ->execute();
  }

  /**
   * Inserts NULL into a column that is NOT NULL.
   */
  protected function notNull(): void {
    $this->connection->insert('test')
      ->fields([
        'name' => NULL,
        'age' => 47,
        'job' => 'Drummer',
      ])
      ->execute();
  }

  /**
   * Violates a unique key with a value that looks like markup.
   *
   * The value ends up in the placeholders, and drivers repeat it in their
   * message as well.
   */
  protected function markupInValues(): void {
    foreach ([1, 2] as $age) {
      $this->connection->insert('test')
        ->fields([
          'name' => self::MARKUP_PAYLOAD,
          'age' => $age,
          'job' => 'Singer',
        ])
        ->execute();
    }
  }

  /**
   * Creates a table that already exists.
   */
  protected function tableExists(): void {
    $this->connection->schema()->createTable('test', [
      'fields' => [
        'id' => ['type' => 'int', 'not null' => TRUE],
      ],
    ]);
  }

  /**
   * Adds a field to a table that does not exist.
   */
  protected function tableMissing(): void {
    $this->connection->schema()->addField('bananas_are_awesome', 'palm_height', [
      'type' => 'int',
      'not null' => FALSE,
    ]);
  }

  /**
   * Rolls back the root transaction while a savepoint is still open.
   */
  protected function transactionOutOfOrder(): void {
    $root = $this->connection->startTransaction();
    // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis
    $savepoint = $this->connection->startTransaction();
    $root->rollBack();
  }

  /**
   * Opens a connection for a database key that is not defined.
   */
  protected function connectionNotDefined(): void {
    Database::getConnection('default', 'bananas');
  }

}
