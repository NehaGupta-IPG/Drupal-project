<?php

declare(strict_types=1);

namespace Drupal\Tests\dblog\Traits;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\dblog\Helper\TestDbLogReader;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Trait that adds a dblog reader to a test.
 */
trait DbLogReaderTrait {

  /**
   * A dblog reader instance.
   */
  protected TestDbLogReader $dbLogReader;

  /**
   * Creates the dblog reader instance.
   *
   * This is called before the regular ::setUp() method.
   * At this time, 'dblog' won't be installed yet, which is fine.
   */
  #[Before(100)]
  protected function setupDbLogReaderEarly(): void {
    $this->dbLogReader = new TestDbLogReader();
    // @todo This should have an opt-out.
    self::$modules[] = 'dblog';
  }

  /**
   * Prepares the dblog reader instance.
   *
   * This is called after the regular ::setUp() method.
   */
  #[Before(-100)]
  protected function setupDbLogReaderLate(): void {
    if (!$this->dbLogReader->isAvailable()) {
      // The test was marked as skipped, or Drupal was not fully installed, or
      // the addition to self::$modules was ignored.
      return;
    }
    // A lot of places log notices for non-failure events.
    $this->dbLogReader
      ->withChannel('php', '!=')
      ->withSeverity(RfcLogLevel::NOTICE)
      ->markAllAsRead();
    // Assert the dblog contains no failures from bootstrap.
    // Then, mark all non-failure log messages as read.
    $this->dbLogReader->sweep();
  }

  /**
   * Asserts that no unexpected failures were logged in the test.
   *
   * This is called before the regular ::tearDown() method, to make sure that
   * all services are still available.
   *
   * This additional check can cause a test to fail, even when the test method
   * itself was passing, revealing problems that would otherwise remain hidden.
   *
   * After a failing test method, this additional check can provide additional
   * information why the failure occurred. In this case, PhpUnit will report
   * both the original test failure and the failure from this tear down method.
   */
  #[After(100)]
  protected function tearDownDbLogReader(): void {
    if (!$this->dbLogReader->isAvailable()) {
      // The test was marked as skipped, or Drupal was not fully installed, or
      // the addition to self::$modules was ignored.
      return;
    }
    // Don't fail for logged http exceptions.
    // These are common for tests that verify access or failing endpoints.
    $this->dbLogReader
      ->withChannel(['access denied', 'page not found', 'client error'])
      ->withSeverity(RfcLogLevel::WARNING)
      ->markAllAsRead();
    // A lot of places log notices for non-failure events.
    $this->dbLogReader
      ->withChannel('php', '!=')
      ->withSeverity(RfcLogLevel::NOTICE)
      ->markAllAsRead();
    $this->dbLogReader->assertNoFailures();
  }

}
