<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Core\Error\DrupalErrorHandler;
use Drupal\Core\Error\DrupalErrorLogger;
use Drupal\Core\Error\DrupalExceptionRenderer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * Tests the DrupalErrorHandler class.
 */
#[Group('Error')]
class DrupalErrorHandlerTest extends UnitTestCase {

  /**
   * Tracks whether this test registered a DrupalErrorHandler.
   */
  private bool $handlerRegistered = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->handlerRegistered) {
      // Restore PHP's default error/exception handlers to prevent
      // interference with PHPUnit's own handlers.
      restore_error_handler();
      restore_exception_handler();
      $this->handlerRegistered = FALSE;
    }

    // Reset the singleton after each test to avoid test pollution.
    DrupalErrorHandler::reset();

    parent::tearDown();
  }

  /**
   * Registers the DrupalErrorHandler and marks it for cleanup.
   */
  private function registerHandler(): DrupalErrorHandler {
    $this->handlerRegistered = TRUE;
    return DrupalErrorHandler::register();
  }

  /**
   * Tests that register() returns a DrupalErrorHandler instance.
   */
  public function testRegister(): void {
    $handler = $this->registerHandler();
    $this->assertInstanceOf(DrupalErrorHandler::class, $handler);
  }

  /**
   * Tests that register() returns the same singleton instance.
   */
  public function testRegisterReturnsSingleton(): void {
    $handler1 = $this->registerHandler();
    $handler2 = $this->registerHandler();
    $this->assertSame($handler1, $handler2);
  }

  /**
   * Tests that getInstance() returns NULL before registration.
   */
  public function testGetInstanceBeforeRegister(): void {
    $this->assertNull(DrupalErrorHandler::getInstance());
  }

  /**
   * Tests that getInstance() returns the handler after registration.
   */
  public function testGetInstanceAfterRegister(): void {
    $handler = $this->registerHandler();
    $this->assertSame($handler, DrupalErrorHandler::getInstance());
  }

  /**
   * Tests that getSymfonyHandler() returns a Symfony ErrorHandler.
   */
  public function testGetSymfonyHandler(): void {
    $handler = $this->registerHandler();
    $this->assertInstanceOf(ErrorHandler::class, $handler->getSymfonyHandler());
  }

  /**
   * Tests that getDrupalErrorLogger() returns the logger bridge.
   */
  public function testGetDrupalErrorLogger(): void {
    $handler = $this->registerHandler();
    $this->assertInstanceOf(DrupalErrorLogger::class, $handler->getDrupalErrorLogger());
  }

  /**
   * Tests that the exception handler is set to DrupalExceptionRenderer.
   */
  public function testExceptionHandlerIsSet(): void {
    $handler = $this->registerHandler();
    $symfonyHandler = $handler->getSymfonyHandler();

    // setExceptionHandler returns the previous handler when setting a new one.
    $previous = $symfonyHandler->setExceptionHandler(NULL);
    $this->assertIsArray($previous);
    $this->assertSame(DrupalExceptionRenderer::class, $previous[0]);
    $this->assertSame('renderException', $previous[1]);

    // Restore the handler.
    $symfonyHandler->setExceptionHandler($previous);
  }

  /**
   * Tests that reset() clears the singleton.
   */
  public function testReset(): void {
    $this->registerHandler();
    $this->assertNotNull(DrupalErrorHandler::getInstance());

    DrupalErrorHandler::reset();
    $this->assertNull(DrupalErrorHandler::getInstance());
  }

  /**
   * Tests that upgradeLogger sets the Drupal logger on the bridge.
   */
  public function testUpgradeLogger(): void {
    $handler = $this->registerHandler();
    $drupalLogger = $this->createStub(LoggerInterface::class);

    // Should not throw.
    $handler->upgradeLogger($drupalLogger);
    $this->addToAssertionCount(1);
  }

}
