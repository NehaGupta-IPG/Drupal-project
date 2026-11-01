<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests rendering of grouped form elements.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class FormElementGroupingTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['datetime', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'test_form_element_grouping';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['group'] = [
      '#type' => 'datetime',
    ];
    $form['grouped_textfield'] = [
      '#type' => 'textfield',
      '#group' => 'group',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Tests that group members are rendered only once.
   */
  public function testGroupedElementRendering(): void {
    $form = \Drupal::formBuilder()->getForm($this);
    // The datetime template renders the element again to render its children.
    // Group members must not be added again during that second rendering pass.
    $this->render($form);

    $this->assertCount(1, $this->cssSelect('input[name="grouped_textfield"]'));
    $this->assertCount(1, $this->cssSelect('#edit-group input[name="grouped_textfield"]'));
  }

}
