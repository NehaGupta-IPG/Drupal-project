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

    $element = [
      '#type' => 'checkboxes',
      '#options' => [
        'test1' => 'Test1',
        'test2' => 'Test2',
        'test3' => 'Test3',
      ],
      '#value' => ['test1'],
    ];

    $complete_form = [
      'test_checkboxes' => $element,
    ];

    $form_state->setCompleteForm($complete_form);
    $element = Checkboxes::processCheckboxes($element, $form_state, $complete_form);

    $this->assertNotNull($element['test1']);
    $this->assertNotNull($element['test2']);
    $this->assertNotNull($element['test3']);
    $this->assertEquals($element['test1']['#type'], 'checkbox');
    $this->assertEquals($element['test1']['#title'], 'Test1');
    $this->assertEquals($element['test2']['#type'], 'checkbox');
    $this->assertEquals($element['test2']['#title'], 'Test2');
    $this->assertEquals($element['test3']['#type'], 'checkbox');
    $this->assertEquals($element['test3']['#title'], 'Test3');
    $this->assertNotNull($element['all_wrapper']);
    $this->assertNotNull($element['all_wrapper']['check_all']);
    $this->assertNotNull($element['all_wrapper']['uncheck_all']);
  }

}
