<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Render\Element\Checkboxes
 * @group Render
 */
class CheckboxesTest extends UnitTestCase {

  /**
   * @covers ::getInfo
   */
  public function testGetInfo(): void {
    $checkboxes = new Checkboxes([], 'test', 'test');
    $info = $checkboxes->getInfo();
    $this->assertArrayHasKey('#input', $info);
    $this->assertArrayHasKey('#pre_render', $info);
    $this->assertArrayHasKey('#process', $info);
    $this->assertArrayHasKey('#theme_wrappers', $info);
  }

  /**
   * @covers ::valueCallback
   *
   * @dataProvider providerTestValueCallback
   */
  public function testValueCallback($expected, $input): void {
    $element = [];
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();
    $this->assertSame($expected, Checkboxes::valueCallback($element, $input, $form_state));
  }

  /**
   * Data provider for testValueCallback().
   */
  public static function providerTestValueCallback() {
    $data = [];
    $data[] = [[], FALSE];
    $data[] = [[], NULL];
    $data[] = [['test' => 'test'], ['test']];
    $data[] = [[], 'test'];
    $data[] = [[], 123];

    return $data;
  }

  /**
   * @covers ::processCheckboxes
   */
  public function testProcessCheckboxes(): void {
    $form_state = new FormState();
    // Create a Checkboxes form element and process it.
    $element = [
      '#type' => 'checkboxes',
      '#options' => [
        'test1' => 'Test1',
        'test2' => 'Test2',
        'test3' => 'Test3',
      ],
      '#check_all' => TRUE,
    ];

    $complete_form = [
      'test_checkboxes' => $element,
    ];

    $form_state->setCompleteForm($complete_form);
    $element = Checkboxes::processCheckboxes($element, $form_state, $complete_form);
    // Check that the checkboxes and checkAll button have been created.
    $this->assertArrayHasKey('test1', $element);
    $this->assertArrayHasKey('test2', $element);
    $this->assertArrayHasKey('test3', $element);
    $this->assertEquals($element['test1']['#type'], 'checkbox');
    $this->assertEquals($element['test1']['#title'], 'Test1');
    $this->assertEquals($element['test2']['#type'], 'checkbox');
    $this->assertEquals($element['test2']['#title'], 'Test2');
    $this->assertEquals($element['test3']['#type'], 'checkbox');
    $this->assertEquals($element['test3']['#title'], 'Test3');
    $this->assertArrayHasKey('all_wrapper', $element);
    // Remove check_all (set it to FALSE) and see that there is no 'check all' button.
    $element = [
      '#type' => 'checkboxes',
      '#options' => [
        'test1' => 'Test1',
      ],
    ];

    $complete_form = [
      'test_checkboxes' => $element,
    ];

    $form_state->setCompleteForm($complete_form);
    $element = Checkboxes::processCheckboxes($element, $form_state, $complete_form);
    $this->assertArrayNotHasKey('all_wrapper', $element);
  }

}
