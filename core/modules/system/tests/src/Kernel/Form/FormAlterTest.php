<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\form_test\Form\FormTestFormIdEqualsBaseFormIdForm;
use Drupal\form_test\Form\FormTestWithBaseFormIdForm;
use Drupal\form_test\Hook\FormTestHooks;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests form alter hooks.
 *
 * @group Form
 */
class FormAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * Tests the programmatic form submission workflow.
   */
  public function testFormAlter(): void {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = \Drupal::service(FormBuilderInterface::class);

    $form = $form_builder->getForm(FormTestWithBaseFormIdForm::class);
    $this->assertSame([
      FormTestHooks::class . '::formAlter',
      FormTestHooks::class . '::formTestWithBaseFormIdBaseFormAlter',
      FormTestHooks::class . '::formTestWithBaseFormIdFormAlter',
    ], $form['#alter_calls'] ?? NULL);

    $notices = $this->callAndCaptureErrors(
      function () use (&$form, $form_builder): void {
        $form = $form_builder->getForm(FormTestFormIdEqualsBaseFormIdForm::class);
      },
    );
    $this->assertSame([
      FormTestHooks::class . '::formAlter',
      FormTestHooks::class . '::formTestFormIdEqualsBaseFormIdFormAlter',
    ], $form['#alter_calls'] ?? NULL);
    $this->assertSame([
      'The method Drupal\form_test\Hook\FormTestHooks::formTestFormIdEqualsBaseFormIdFormAlter() is registered for more than one of the alter hooks [&#039;form_alter&#039;, &#039;form_form_test_form_id_equals_base_form_id_form_alter&#039;, &#039;form_form_test_form_id_equals_base_form_id_form_alter&#039;] from the current ->alter() call. Only one instance will be part of the implementation list for this hook combination.',
    ], $notices);
  }

  /**
   * Invokes a callback and captures errors of a specific level.
   *
   * @param \Closure(): void $callback
   *   Callback that may or may not produce notices.
   * @param int $captured_level
   *   The error level for captured errors.
   *   Errors with a different type are not captured.
   *
   * @return list<string>
   *   Captured error messages.
   */
  protected function callAndCaptureErrors(\Closure $callback, int $captured_level = E_USER_NOTICE): array {
    $messages = [];
    $previous_error_handler = get_error_handler();
    $capturing_error_handler = function (int $level, string $message, ...$args) use (&$messages, $previous_error_handler, $captured_level): bool {
      if ($level !== $captured_level) {
        return $previous_error_handler($level, $message, ...$args);
      }
      $messages[] = $message;
      return TRUE;
    };
    $previous_error_handler = set_error_handler($capturing_error_handler);
    try {
      $callback();
    }
    finally {
      restore_error_handler();
    }
    return $messages;
  }

}
