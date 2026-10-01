<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests messages on form elements.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class FormElementInlineErrorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings = Settings::getAll();
    $settings['enable_inline_error_handling'] = TRUE;
    new Settings($settings);
  }

    /**
   * Tests that no inline form errors are shown when disabled for a form.
   */
  public function testDisplayErrorMessagesNotInline(): void {
    $form_id = 'test';

    $form = [
      '#parents' => [],
      '#disable_inline_form_errors' => TRUE,
      '#array_parents' => [],
    ];
    $form['test'] = [
      '#type' => 'textfield',
      '#title' => 'Test',
      '#parents' => ['test'],
      '#id' => 'edit-test',
      '#array_parents' => ['test'],
    ];
    $form_state = new FormState();

    \Drupal::formBuilder()->prepareForm($form_id, $form, $form_state);
    \Drupal::formBuilder()->processForm($form_id, $form, $form_state);

    // Just test if the #error_no_message property is TRUE. FormErrorHandlerTest
    // tests if the property actually hides the error message.
    $this->assertTrue($form['test']['#error_no_message']);
  }

}
