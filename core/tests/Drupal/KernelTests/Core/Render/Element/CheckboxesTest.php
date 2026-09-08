<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Render\Element;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Checkboxes render element.
 */
#[CoversClass(Checkboxes::class)]
#[Group('Render')]
#[RunTestsInSeparateProcesses]
class CheckboxesTest extends KernelTestBase implements FormInterface {

  /**
   * The checkboxes element to build, keyed by form key.
   *
   * @var array
   */
  protected array $element = [
    '#type' => 'checkboxes',
    '#options' => [
      'test1' => 'Test1',
      'test2' => 'Test2',
      'test3' => 'Test3',
    ],
    '#check_all' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return __CLASS__;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['test_checkboxes'] = $this->element;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Tests that #check_all generates the select-all checkbox.
   */
  public function testProcessCheckboxesWithCheckAll(): void {
    $form_state = new FormState();
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);
    $element = $form_state->getCompleteForm()['test_checkboxes'];

    $this->assertArrayHasKey('test1', $element);
    $this->assertArrayHasKey('test2', $element);
    $this->assertArrayHasKey('test3', $element);
    $this->assertEquals('checkbox', $element['test1']['#type']);
    $this->assertEquals('Test1', $element['test1']['#title']);
    $this->assertEquals('checkbox', $element['test2']['#type']);
    $this->assertEquals('Test2', $element['test2']['#title']);
    $this->assertEquals('checkbox', $element['test3']['#type']);
    $this->assertEquals('Test3', $element['test3']['#title']);
    $this->assertArrayHasKey('all_wrapper', $element);
  }

  /**
   * Tests that without #check_all, no select-all checkbox is generated.
   */
  public function testProcessCheckboxesWithoutCheckAll(): void {
    $this->element = [
      '#type' => 'checkboxes',
      '#options' => [
        'test1' => 'Test1',
      ],
    ];

    $form_state = new FormState();
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);
    $element = $form_state->getCompleteForm()['test_checkboxes'];

    $this->assertArrayNotHasKey('all_wrapper', $element);
  }

  /**
   * Tests that submitted checkbox values are reflected in the form state.
   */
  public function testProcessCheckboxesSubmission(): void {
    $form_state = (new FormState())
      ->setValues([
        'test_checkboxes' => [
          'test1' => 'test1',
        ],
      ]);
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);

    $this->assertCount(0, $form_state->getErrors());
    $this->assertEquals([
      'test1' => 'test1',
      'test2' => 0,
      'test3' => 0,
    ], $form_state->getValue('test_checkboxes'));
  }

}
