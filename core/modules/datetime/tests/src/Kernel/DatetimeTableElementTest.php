<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests datetime elements nested inside table elements.
 */
#[Group('datetime')]
#[RunTestsInSeparateProcesses]
class DatetimeTableElementTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['datetime', 'system'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('date_format');
    $this->installConfig(['system']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'datetime_table_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        'Datetime',
      ],
    ];
    $form['table'][0]['datetime'] = [
      '#type' => 'datetime',
      '#title' => 'Datetime',
      '#default_value' => new DrupalDateTime('2026-01-01 12:00:00'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];
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
   * Test that a datetime element in a table keeps its parsed value.
   */
  public function testDatetimeInTable(): void {
    $form_builder = $this->container->get('form_builder');
    $form_state = new FormState();
    $form_state->setValues([
      'table' => [
        0 => [
          'datetime' => [
            'date' => '2026-01-01',
            'time' => '12:00:00',
          ],
        ],
      ],
      'op' => 'Submit',
    ]);
    $form_builder->submitForm($this, $form_state);
    $this->assertEmpty($form_state->getErrors());
    $value = $form_state->getValue(['table', 0, 'datetime']);
    $this->assertInstanceOf(DrupalDateTime::class, $value);
    $this->assertSame('2026-01-01 12:00:00', $value->format('Y-m-d H:i:s'));
  }

}
