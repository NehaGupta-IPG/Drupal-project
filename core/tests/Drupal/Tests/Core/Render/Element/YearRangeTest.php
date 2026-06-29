<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\YearRange;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the year range element.
 */
#[Group('render')]
#[CoversClass(YearRange::class)]
class YearRangeTest extends UnitTestCase {

  /**
   * @legacy-covers ::valueCallback
   */
  public function testValueCallback(): void {
    $expected = ['years_back' => '-3', 'years_forward' => '+3'];
    $element = ['#default_value' => '-3:+3'];
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();
    $this->assertSame($expected, YearRange::valueCallback($element, FALSE, $form_state));
  }

  /**
   * @legacy-covers ::processRange
   */
  public function testProcessRange(): void {
    $form_state = new FormState();

    $element = [
      '#value' => [
        'years_back' => '-3',
        'years_forward' => '+3',
      ],
    ];

    $complete_form = [
      'test_source' => [
        '#type' => 'textfield',
        '#id' => 'source',
      ],
      'test_year_range' => $element,
    ];

    $form_state->setCompleteForm($complete_form);
    $element = YearRange::processRange($element, $form_state, $complete_form);

    $expected = [
      '#type' => 'textfield',
      '#size' => 10,
      '#maxsize' => 10,
      '#description' => 'Enter a relative value (-9, +9) or an absolute year such as 2015.',
    ];
    $expected_years_back = ['#title' => 'Starting year', '#default_value' => '-3'] + $expected;
    $expected_years_forward = ['#title' => 'Ending year', '#default_value' => '+3'] + $expected;

    $this->assertEquals($expected_years_back, $element['years_back']);
    $this->assertEquals($expected_years_forward, $element['years_forward']);
    $this->assertTrue($element['#tree']);
  }

}
