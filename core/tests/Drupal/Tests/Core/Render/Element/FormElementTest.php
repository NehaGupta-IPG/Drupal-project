<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Render\Element\FormElementBase
 * @group Render
 */
class FormElementTest extends UnitTestCase {

  /**
   * @covers ::setAttributes
   *
   * @dataProvider providerTestSetAttributes
   */
  public function testSetAttributes(array $element, array $class, array $expected): void {
    RenderElementBase::setAttributes($element, $class);
    $this->assertSame($expected, $element);
  }

  /**
   * Provides test data for testSetAttributes().
   */
  public static function providerTestSetAttributes(): array {
    return [
      'Required' => [
        'element' => [
          '#type' => 'textfield',
          '#required' => TRUE,
        ],
        'class' => [],
        'expected' => [
          '#type' => 'textfield',
          '#required' => TRUE,
          '#attributes' => [
            'class' => [
              'required',
            ],
            'required' => 'required',
          ],
        ],
      ],
      'Parent with error' => [
        'element' => [
          '#type' => 'textfield',
          '#parents' => ['dummy_parent'],
          '#errors' => 'invalid',
          '#validated' => TRUE,
        ],
        'class' => [],
        'expected' => [
          '#type' => 'textfield',
          '#parents' => ['dummy_parent'],
          '#errors' => 'invalid',
          '#validated' => TRUE,
          '#attributes' => [
            'class' => [
              'error',
            ],
            'aria-invalid' => 'true',
          ],
        ],
      ],
    ];
  }

}
