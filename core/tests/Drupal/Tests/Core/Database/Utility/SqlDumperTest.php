<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database\Utility;

use Drupal\Core\Database\SqlExecutionInfo;
use Drupal\Core\Database\Utility\SqlDumper;
use Drupal\Tests\Core\Database\Utility\Stub\StubUnformattedSqlDumper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
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
   * Tests that no information produces no dump.
   */
  public function testNoExecutionInfo(): void {
    $this->assertSame('', (new SqlDumper())->export(NULL));
    $this->assertSame('', (new StubUnformattedSqlDumper())->export(NULL));
  }

}
