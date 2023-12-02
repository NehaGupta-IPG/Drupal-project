<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Shutdown\ShutdownHandler;

/**
 * Tests Drupal\KernelTests\KernelTestBase.
 */
#[CoversClass(KernelTestBase::class)]
#[Group('PHPUnit')]
#[Group('Test')]
#[Group('KernelTests')]
#[RunTestsInSeparateProcesses]
class KernelTestBaseShutdownTest extends KernelTestBase {

  /**
   * Indicates which shutdown functions are expected to be called.
   *
   * @var array
   */
  protected $expectedShutdownCalled;

  /**
   * Indicates which shutdown functions have been called.
   *
   * @var array
   */
  protected static $shutdownCalled;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Initialize static variable prior to testing.
    self::$shutdownCalled = [];
    parent::setUp();
  }

  /**
   * Tests shutdown function.
   *
   * @legacy-covers ::assertPostConditions
   */
  public function testShutdownFunction(): void {
    $this->expectedShutdownCalled = ['shutdownFunction', 'shutdownFunction2'];
    ShutdownHandler::getInstance()->add([$this, 'shutdownFunction']);
  }

  /**
   * Tests no shutdown function.
   *
   * @legacy-covers ::assertPostConditions
   */
  public function testNoShutdownFunction(): void {
    $this->expectedShutdownCalled = [];
  }

  /**
   * Registers that this shutdown function has been called.
   */
  public function shutdownFunction(): void {
    self::$shutdownCalled[] = 'shutdownFunction';
    ShutdownHandler::getInstance()->add([$this, 'shutdownFunction2']);
  }

  /**
   * Registers that this shutdown function has been called.
   */
  public function shutdownFunction2(): void {
    self::$shutdownCalled[] = 'shutdownFunction2';
  }

  /**
   * {@inheritdoc}
   */
  protected function assertPostConditions(): void {
    parent::assertPostConditions();
    $this->assertSame($this->expectedShutdownCalled, self::$shutdownCalled);
  }

}
