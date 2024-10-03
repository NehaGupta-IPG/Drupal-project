<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textarea;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Render\Element\Textarea.
 */
#[CoversClass(Textarea::class)]
#[Group('Render')]
class TextareaTest extends UnitTestCase {

  /**
   * Tests value callback.
   */
  #[DataProvider('providerTestValueCallback')]
  public function testValueCallback($expected, $input, array $element = []): void {
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();
    $this->assertSame($expected, Textarea::valueCallback($element, $input, $form_state));
  }

  /**
   * Data provider for testValueCallback().
   */
  public static function providerTestValueCallback(): array {
    return [
      'False' => [
        NULL,
        FALSE,
        ['#normalize_newlines' => TRUE],
      ],
      'NULL' => [
        NULL,
        NULL,
        ['#normalize_newlines' => TRUE],
      ],
      'empty array' => [
        '',
        ['test'],
        ['#normalize_newlines' => TRUE],
      ],
      'string' => [
        'test',
        'test',
        ['#normalize_newlines' => TRUE],
      ],
      'integer' => [
        '123',
        123,
        ['#normalize_newlines' => TRUE],
      ],
      'normalize new lines' => [
        "some\ndifferent\nline\nendings",
        "some\r\ndifferent\rline\nendings",
        ['#normalize_newlines' => TRUE],
      ],
      'do not normalize new lines' => [
        "some\r\ndifferent\rline\nendings",
        "some\r\ndifferent\rline\nendings",
        ['#normalize_newlines' => FALSE],
      ],
    ];
  }

}
