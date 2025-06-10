<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\HandlerBase;

/**
 * Unit test for caseTransform() in HandlerBase.
 *
 * @group views
 */
class CaseTransformTest extends UnitTestCase {

  /**
   * Provides test cases for caseTransform().
   *
   * @return array
   *   An array of test cases, each containing input,
   *   transformation type, and expected output.
   */
  public static function transformValueProvider(): array {
    return [
      ['exploration', 'upper', 'EXPLORATION'],
      ['DISCOVERY', 'lower', 'discovery'],
      ['journey', 'ucfirst', 'Journey'],
      ['new horizons', 'ucwords', 'New Horizons'],
      [NULL, 'upper', ''],
      ['', 'upper', ''],
      [123, 'upper', ''],
    ];
  }

  /**
   * Tests caseTransform() with various edge-case values.
   *
   * @dataProvider transformValueProvider
   */
  public function testCaseTransform(mixed $input, string $option, string $expected): void {
    // Mocking HandlerBase with dummy constructor values.
    $handler = $this->getMockBuilder(HandlerBase::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Using reflection to call the protected method.
    $method = new \ReflectionMethod($handler, 'caseTransform');
    $method->setAccessible(TRUE);

    $this->assertSame($expected, $method->invoke($handler, $input, $option));
  }

}
