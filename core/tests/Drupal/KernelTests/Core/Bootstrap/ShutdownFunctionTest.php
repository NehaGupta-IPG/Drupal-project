<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Bootstrap;

use Drupal\Core\Shutdown\ShutdownHandler;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests.
 */
#[Group('Bootstrap')]
#[RunTestsInSeparateProcesses]
class ShutdownFunctionTest extends KernelTestBase {

  /**
   * Flag to indicate if ::shutdownOne() called.
   *
   * @var bool
   */
  protected $shutDownOneCalled = FALSE;

  /**
   * Flag to indicate if ::shutdownTwo() called.
   *
   * @var bool
   */
  protected $shutDownTwoCalled = FALSE;

  /**
   * Tests that shutdown functions can be added by other shutdown functions.
   */
  public function testShutdownFunctionInShutdownFunction(): void {
    // Ensure there are no shutdown functions registered before starting the
    // test.
    $instance = ShutdownHandler::getInstance();
    $this->assertEmpty($instance->get());
    // Register a shutdown function that, when called, will register another
    // shutdown function.
    $instance->add([$this, 'shutdownOne']);
    $this->assertCount(1, $instance->get());

    // Simulate the Drupal shutdown.
    $instance->shutdown();

    // Test that the expected functions are called.
    $this->assertTrue($this->shutDownOneCalled);
    $this->assertTrue($this->shutDownTwoCalled);
    $this->assertCount(2, $instance->get());
  }

  /**
   * Tests shutdown functions by registering another shutdown function.
   */
  public function shutdownOne(): void {
    ShutdownHandler::getInstance()->add([$this, 'shutdownTwo']);
    $this->shutDownOneCalled = TRUE;
  }

  /**
   * Tests shutdown functions by being registered during shutdown.
   */
  public function shutdownTwo(): void {
    $this->shutDownTwoCalled = TRUE;
  }

}
