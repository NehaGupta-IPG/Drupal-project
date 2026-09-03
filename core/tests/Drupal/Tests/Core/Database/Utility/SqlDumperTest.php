<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database\Utility;

use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Database\Utility\SqlDumper;
use Drupal\Tests\Core\Database\Utility\Stub\StubUnformattedSqlDumper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Database\Utility\SqlDumper.
 */
#[CoversClass(SqlDumper::class)]
#[Group('Database')]
class SqlDumperTest extends UnitTestCase {

  /**
   * A payload that must never reach a page unescaped.
   */
  const XSS_PAYLOAD = "<script>alert('xss')</script>";

  /**
   * Returns execution information carrying the payload in a placeholder.
   */
  protected function executionInfo(): SqlExecutionInfo {
    return new SqlExecutionInfo(
      'SELECT name FROM {users} WHERE name = :db_condition_placeholder_0',
      [':db_condition_placeholder_0' => self::XSS_PAYLOAD],
      ['allow_delimiter_in_query' => FALSE],
    );
  }

  /**
   * Tests that the formatted HTML dump escapes the placeholder values.
   */
  public function testFormattedHtmlDumpIsEscaped(): void {
    $dump = (new SqlDumper())->export($this->executionInfo(), FALSE, TRUE);

    $this->assertStringNotContainsString(self::XSS_PAYLOAD, $dump);
    $this->assertStringContainsString('&lt;script&gt;', $dump);
  }

  /**
   * Tests that the minimal HTML dump escapes the placeholder values.
   *
   * The SQL formatter is a development dependency, so production sites get
   * this dump. Callers mark it as safe markup, so it has to be escaped.
   */
  public function testMinimalHtmlDumpIsEscaped(): void {
    $dump = (new StubUnformattedSqlDumper())->export($this->executionInfo(), FALSE, TRUE);

    $this->assertStringNotContainsString(self::XSS_PAYLOAD, $dump);
    $this->assertStringContainsString('&lt;script&gt;', $dump);
    // The statement is still reported.
    $this->assertStringContainsString('SELECT name FROM {users}', $dump);
  }

  /**
   * Tests that the minimal CLI dump is not escaped.
   *
   * Terminal output has no markup, so escaping would only make the values
   * harder to read.
   */
  public function testMinimalCliDumpIsNotEscaped(): void {
    $dump = (new StubUnformattedSqlDumper())->export($this->executionInfo(), FALSE, FALSE);

    $this->assertStringContainsString(self::XSS_PAYLOAD, $dump);
    $this->assertStringNotContainsString('&lt;script&gt;', $dump);
  }

  /**
   * Tests that a statement the formatter misreads is reported as executed.
   *
   * The formatter reads a backslash inside a single quoted string as an escape
   * character, which is MySQL behaviour rather than SQL standard. The SQLite
   * driver ends a LIKE condition with ESCAPE '\\', taken literally, so the
   * formatter loses track of the string and warns about parentheses the
   * statement does close.
   *
   * @param string $escape
   *   The escape literal the driver appends to a LIKE condition.
   * @param bool $reformatted
   *   Whether the statement is expected to be reformatted.
   */
  #[DataProvider('providerLikeEscapeLiteral')]
  public function testStatementTheFormatterMisreads(string $escape, bool $reformatted): void {
    $sql = 'SELECT "t"."id" FROM "test" "t" WHERE ("t"."name" LIKE :db_condition_placeholder_0 ESCAPE '
      . $escape . ') AND ("t"."age" > :db_condition_placeholder_1) ORDER BY "t"."age" DESC';
    $info = new SqlExecutionInfo($sql, [':db_condition_placeholder_0' => '%a%']);

    $dump = (new SqlDumper())->export($info, FALSE, FALSE);

    // Whichever way the statement is rendered, all of it is reported and the
    // formatter does not claim it is malformed.
    $this->assertStringContainsString(
      preg_replace('/\s+/', '', $sql),
      preg_replace('/\s+/', '', $dump),
    );
    $this->assertStringNotContainsString('WARNING', $dump);
    // A statement the formatter reproduces is reformatted; the other one is
    // reported as it stands, since reformatting is only cosmetic.
    $this->assertSame($reformatted, str_contains($dump, "\n  AND ("));
  }

  /**
   * Provides test cases for ::testStatementTheFormatterMisreads().
   */
  public static function providerLikeEscapeLiteral(): array {
    return [
      // \Drupal\Core\Database\Query\Condition::$conditionOperatorMap, for a
      // database that reads a backslash in a string as an escape character.
      'two backslashes' => ["'\\\\'", TRUE],
      // \Drupal\sqlite\Driver\Database\sqlite\Connection::$sqlOperators,
      // for one that takes it literally.
      'one backslash' => ["'\\'", FALSE],
    ];
  }

  /**
   * Tests that no information produces no dump.
   */
  public function testNoExecutionInfo(): void {
    $this->assertSame('', (new SqlDumper())->export(NULL, FALSE, FALSE));
    $this->assertSame('', (new StubUnformattedSqlDumper())->export(NULL, FALSE, FALSE));
  }

}
