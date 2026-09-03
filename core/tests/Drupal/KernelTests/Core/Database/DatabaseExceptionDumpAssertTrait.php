<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Database\Utility\SqlDumper;
use Drupal\Tests\Core\Database\Utility\Stub\StubUnformattedSqlDumper;

/**
 * Asserts what the error handlers get out of a database exception.
 *
 * The dump is produced while an exception is being reported, in three
 * flavours: HTML for the error page, decorated CLI for a terminal, and plain
 * CLI for a redirected stream or a JUnit log. None of them may fail, whatever
 * the exception carries.
 *
 * @see \Drupal\Core\Database\Utility\SqlDumper::export()
 * @see \Drupal\Core\EventSubscriber\FinalExceptionSubscriber::onException()
 * @see \Drupal\Core\Command\DrupalApplication::doRenderThrowable()
 * @see \Drupal\Tests\DrupalTestCaseTrait::transformException()
 */
trait DatabaseExceptionDumpAssertTrait {

  /**
   * Returns the execution information the error handlers would dump.
   *
   * Resolved the way the handlers resolve it: through the whole chain of
   * previous throwables, since a database exception thrown by Drupal rather
   * than by a driver carries no information, not every class implementing
   * \Drupal\Core\Database\DatabaseException extends
   * \Drupal\Core\Database\DatabaseExceptionBase, and the layer that ran the
   * query may have wrapped the exception in one of its own.
   *
   * @param \Throwable $e
   *   The exception to read.
   *
   * @return \Drupal\Core\Database\SqlExecutionInfo|null
   *   The SQL execution information, or NULL if the chain carries none.
   */
  protected function executionInfo(\Throwable $e): ?SqlExecutionInfo {
    return SqlExecutionInfo::fromThrowable($e);
  }

  /**
   * Asserts that an exception without execution information produces no dump.
   *
   * @param \Throwable $e
   *   The exception thrown by the failing operation.
   */
  protected function assertNoDump(\Throwable $e): void {
    $this->assertInstanceOf(DatabaseException::class, $e);
    $info = $this->executionInfo($e);
    $this->assertNull($info, 'The exception carries no SQL execution information.');

    // Every flavour, since a caller passing the flags explicitly must get the
    // same empty result as one letting them be sniffed. The minimal dump a
    // production site gets is checked too.
    foreach ([new SqlDumper(), new StubUnformattedSqlDumper()] as $dumper) {
      $this->assertSame('', $dumper->export($info, FALSE, TRUE));
      $this->assertSame('', $dumper->export($info, TRUE, FALSE));
      $this->assertSame('', $dumper->export($info, FALSE, FALSE));
    }
  }

  /**
   * Asserts the three dumps of an exception with execution information.
   *
   * @param \Throwable $e
   *   The exception thrown by the failing operation.
   *
   * @return \Drupal\Core\Database\SqlExecutionInfo
   *   The information that was dumped, for further assertions on the case.
   */
  protected function assertDump(\Throwable $e): SqlExecutionInfo {
    $info = $this->executionInfo($e);
    $this->assertNotNull($info, 'The exception carries SQL execution information.');
    $this->assertNotSame('', trim($info->sqlString), 'The reported statement is not empty.');

    // The message keeps the statement in compact form, so that it still
    // reaches the log and any consumer rendering the throwable itself. Only
    // the appended part is a single line: a driver message can span lines, as
    // PostgreSQL does when it points at the offending token.
    $this->assertStringEndsWith(': ' . $info->toCompactString(), $e->getMessage());
    $this->assertStringNotContainsString("\n", $info->toCompactString());

    $dumper = new SqlDumper();
    $dumps = [
      'HTML' => $dumper->export($info, FALSE, TRUE),
      'decorated CLI' => $dumper->export($info, TRUE, FALSE),
      'plain CLI' => $dumper->export($info, FALSE, FALSE),
    ];
    foreach ($dumps as $flavour => $dump) {
      $this->assertStringContainsString('SQL statement', $dump, "The $flavour dump reports the statement.");
    }

    // A terminal gets colors. A redirected stream must not, and neither must
    // the JUnit log that run-tests.sh parses: PHPUnit strips the escape byte
    // from the XML but leaves the rest of the sequence behind.
    $this->assertStringContainsString("\x1b[0m", $dumps['decorated CLI']);
    $this->assertStringNotContainsString("\x1b", $dumps['plain CLI']);
    $this->assertStringNotContainsString("\x1b", $dumps['HTML']);

    // The SQL formatter is a development dependency, so a production site
    // gets the minimal dump instead. It reports the statement as it was
    // executed, and the whole of it is escaped for a page, since the caller
    // marks the dump as safe markup either way.
    $fallback = new StubUnformattedSqlDumper();
    $minimal = $fallback->export($info, FALSE, FALSE);
    $this->assertStringContainsString($info->sqlString, $minimal);
    $this->assertSame(Html::escape($minimal), $fallback->export($info, FALSE, TRUE));

    return $info;
  }

}
