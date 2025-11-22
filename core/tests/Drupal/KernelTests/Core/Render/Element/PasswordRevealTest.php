<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\PasswordUnmask;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Render\Element\PasswordUnmask.
 */
#[CoversClass(PasswordUnmask::class)]
#[Group('Render')]
class PasswordRevealTest extends UnitTestCase {

  /**
   * Tests value callback.
   *
   * @covers ::valueCallback
   */
  #[DataProvider('providerTestValueCallback')]
  public function testValueCallback($expected, $input): void {
    $element = [];
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();
    $this->assertSame($expected, PasswordUnmask::valueCallback($element, $input, $form_state));
  }

  /**
   * Data provider for the testValueCallback().
   */
  public static function providerTestValueCallback(): array {
    $data = [];
    $data[] = [NULL, FALSE];
    $data[] = [NULL, NULL];
    $data[] = ['', ['test']];
    $data[] = ['test', 'test'];
    $data[] = ['123', 123];

    return $data;
  }

}
