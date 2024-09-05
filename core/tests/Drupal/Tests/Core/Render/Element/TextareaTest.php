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
    // Make sure element has its defaults added.
    // @see \Drupal\Core\Render\Element\Textarea::getInfo()
    $element += [
      '#cols' => 60,
      '#rows' => 5,
      '#resizable' => 'vertical',
      '#maxlength' => NULL,
      '#normalize_newlines' => TRUE,
    ];
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();
    $this->assertSame($expected, Textarea::valueCallback($element, $input, $form_state));
  }

  /**
   * Data provider for testValueCallback().
   */
  public static function providerTestValueCallback(): array {
    $data = [];
    $data[] = [NULL, FALSE];
    $data[] = [NULL, NULL];
    $data[] = ['', ['test']];
    $data[] = ['test', 'test'];
    $data[] = ['123', 123];
    // New lines normalization is enabled (default).
    $data[] = [
      "some\ndifferent\nline\nendings",
      "some\r\ndifferent\rline\nendings",
    ];
    // New lines normalization is disabled.
    $data[] = [
      "some\r\ndifferent\rline\nendings",
      "some\r\ndifferent\rline\nendings",
      ['#normalize_newlines' => FALSE],
    ];

    return $data;
  }

}
