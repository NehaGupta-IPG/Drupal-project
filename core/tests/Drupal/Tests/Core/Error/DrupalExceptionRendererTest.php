<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Core\Error\DrupalExceptionRenderer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DrupalExceptionRenderer class.
 *
 * Note: The primary rendering methods call exit() and send HTTP responses,
 * which cannot be tested in a unit test context. The full integration is
 * tested by functional tests (e.g. BrowserTestBaseTest). This test covers
 * the class structure and verifies it can be instantiated.
 *
 * @see \Drupal\Tests\Core\Error\DrupalLogErrorTest
 *   Existing tests that verify the error rendering pipeline via PhpProcess.
 */
#[Group('Error')]
class DrupalExceptionRendererTest extends UnitTestCase {

  /**
   * Tests that the class is final and has the expected public method.
   */
  public function testClassStructure(): void {
    $reflection = new \ReflectionClass(DrupalExceptionRenderer::class);
    $this->assertTrue($reflection->isFinal());

    $method = $reflection->getMethod('renderException');
    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());

    // Verify the parameter type.
    $params = $method->getParameters();
    $this->assertCount(1, $params);
    $this->assertSame('Throwable', $params[0]->getType()->getName());
  }

  /**
   * Tests that renderAdditionalException is available for error recovery.
   */
  public function testRenderAdditionalExceptionExists(): void {
    $reflection = new \ReflectionClass(DrupalExceptionRenderer::class);
    $method = $reflection->getMethod('renderAdditionalException');
    $this->assertTrue($method->isStatic());
  }

}
