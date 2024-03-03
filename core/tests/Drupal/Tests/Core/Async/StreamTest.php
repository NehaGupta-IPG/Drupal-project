<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Async;

use Drupal\Tests\UnitTestCase;
use Revolt\EventLoop;
use function Drupal\Core\Async\stream;

/**
 * Test the async stream function.
 *
 * @group Async
 * @covers \Drupal\Core\Async\stream
 */
class StreamTest extends UnitTestCase {

  /**
   * Test that it doesn't complain if operations aren't actually async.
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
   * Test that it properly deals with deferred operations.
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
   * Test that it handles delayed operations.
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
   * Test that operations work if an operation suspends again.
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
   * Create a deferred operation.
   *
   * @template T
   *
   * @param T $returnValue
   *   The return value.
   *
   * @return T
   *   The return value after suspension.
   */
  protected function executeDeferredOperation(mixed $returnValue) : mixed {
    $suspension = EventLoop::getSuspension();
    EventLoop::defer(fn () => $suspension->resume($returnValue));
    return $suspension->suspend();
  }

  /**
   * Create a delay operation.
   *
   * @template T
   *
   * @param float $delay
   *   The delay in seconds.
   * @param T $returnValue
   *   The return value.
   *
   * @return T
   *   The return value after the delay.
   */
  protected function executeDelayedOperation(float $delay, mixed $returnValue) : mixed {
    $suspension = EventLoop::getSuspension();
    EventLoop::delay($delay, fn () => $suspension->resume($returnValue));
    return $suspension->suspend();
  }

}
