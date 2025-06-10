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
   */
  public static function transformValueProvider() {
    return [
      ['hello', 'upper', 'HELLO'],
      ['HELLO', 'lower', 'hello'],
      ['hello', 'ucfirst', 'Hello'],
      ['hello world', 'ucwords', 'Hello World'],
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
  public function testCaseTransform($input, $option, $expected) {
    // Mocking HandlerBase with dummy constructor values.
    $handler = $this->getMockBuilder(HandlerBase::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Using reflection to call the protected method
    $method = new \ReflectionMethod($handler, 'caseTransform');
    $method->setAccessible(true);

    $this->assertSame($expected, $method->invoke($handler, $input, $option));
  }

}
