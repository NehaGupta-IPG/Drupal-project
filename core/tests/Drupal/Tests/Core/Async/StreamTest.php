<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Revolt\EventLoop;
use function Drupal\Core\Async\stream;

/**
 * Test the async stream function.
 *
 * @group Async
 */
#[CoversFunction("\Drupal\Core\Async\stream")]
class StreamTest extends UnitTestCase {

  /**
   * Tests that stream() doesn't complain if operations aren't actually async.
   */
  public function testHandlesSynchronousOperations() : void {
    $output = [];
    $operations = [
      fn () => "1",
      fn () => "2",
    ];

    // Loop over the operations to get the result.
    foreach (stream($operations) as $result) {
      $this->assertTrue($result->isOk());
      $output[] = $result->getValue();
    }

    $this->assertEquals(["1", "2"], $output);
  }

  /**
   * Tests that stream() properly deals with deferred operations.
   *
   * Results should arrive in order as guaranteed by the EventLoop.
   */
  public function testHandlesDeferredOperations() : void {
    $output = [];
    $operations = [
      fn () => $this->executeDeferredOperation("1"),
      fn () => $this->executeDeferredOperation("2"),
    ];

    // Loop over the operations to get the result.
    foreach (stream($operations) as $result) {
      $this->assertTrue($result->isOk());
      $output[] = $result->getValue();
    }

    $this->assertEquals(["1", "2"], $output);
  }

  /**
   * Tests that stream() handles delayed operations.
   *
   * Results should arrive in order of completion as guaranteed by the
   * EventLoop.
   */
  public function testsOutOfOrderOperations() : void {
    $output = [];
    $operations = [
      fn () => $this->executeDelayedOperation(0.002, "1"),
      fn () => $this->executeDelayedOperation(0.0001, "2"),
    ];

    // Loop over the operations to get the result.
    foreach (stream($operations) as $result) {
      $this->assertTrue($result->isOk());
      $output[] = $result->getValue();
    }

    $this->assertEquals(["2", "1"], $output);
  }

  /**
   * Tests that operations work if an operation suspends again.
   */
  public function testNestedOperation() : void {
    $operations = [
      function () {
        $suspension = EventLoop::getSuspension();
        EventLoop::defer(static function () use ($suspension) {
          $s2 = EventLoop::getSuspension();
          EventLoop::delay(0.1, fn () => $s2->resume("Foo"));
          $suspension->resume($s2->suspend());
        });
        return $suspension->suspend();
      },
    ];

    $output = [];
    foreach (stream($operations) as $result) {
      $this->assertTrue($result->isOk());
      $output[] = $result->getValue();
    }

    $this->assertEquals(["Foo"], $output);
  }

  /**
   * Creates a deferred operation.
   *
   * @param T $returnValue
   *   The return value.
   *
   * @return T
   *   The return value after suspension.
   *
   * @template T
   */
  protected function executeDeferredOperation(mixed $returnValue) : mixed {
    $suspension = EventLoop::getSuspension();
    EventLoop::defer(fn () => $suspension->resume($returnValue));
    return $suspension->suspend();
  }

  /**
   * Creates a delayed operation.
   *
   * @param float $delay
   *   The delay in seconds.
   * @param T $returnValue
   *   The return value.
   *
   * @return T
   *   The return value after the delay.
   *
   * @template T
   */
  protected function executeDelayedOperation(float $delay, mixed $returnValue) : mixed {
    $suspension = EventLoop::getSuspension();
    EventLoop::delay($delay, fn () => $suspension->resume($returnValue));
    return $suspension->suspend();
  }

}
