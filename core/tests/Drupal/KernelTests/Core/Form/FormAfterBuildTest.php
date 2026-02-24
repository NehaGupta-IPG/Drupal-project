<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Form;

use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests forms with an #after_build callback.
 */
#[CoversClass(FormBuilder::class)]
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class FormAfterBuildTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * Catches warnings and notices.
   *
   * @var string[]
   */
  private static array $caughtErrors = [];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'form_after_build_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => 'Textfield',
      '#required' => TRUE,
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
   * Tests a form with an #after_build that adds an element with no '#parents'.
   */
  public function testUndefinedParentsInAfterBuild(): void {
    $form_state = new FormState();
    $form_builder = $this->container->get('form_builder');

    $error_message = NULL;

    try {
      $form_builder->submitForm($this, $form_state);
    }
    catch (\Throwable $e) {
      $error_message = $e->getMessage();
    }

    $this->assertTrue(
      empty($error_message),
      "The following error occurred during the form submission: {$error_message}"
    );
  }

}
