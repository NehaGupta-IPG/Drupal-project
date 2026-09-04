<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Security\UntrustedCallbackException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Managed file element test.
 *
 * @see \Drupal\file\Element\ManagedFile
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class ManagedFileTest extends FileManagedUnitTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'form_test_managed_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $file_value_callback = 'managedFileValueCallbackTrusted'): array {
    $form['managed_file'] = [
      '#type' => 'managed_file',
      '#file_value_callbacks' => [[$this, $file_value_callback]],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * A #file_value_callbacks callback trusted via the TrustedCallback attribute.
   */
  #[TrustedCallback]
  public function managedFileValueCallbackTrusted(array &$element, $input, FormStateInterface $form_state): void {
    $form_state->set('managedFileValueCallbackExecuted', TRUE);
  }

  /**
   * An untrusted #file_value_callbacks callback.
   *
   * This is never actually invoked: an exception is thrown before an
   * untrusted callback is called.
   */
  public function managedFileValueCallback(array &$element, $input, FormStateInterface $form_state): void {}

  /**
   * Submits the test form, using the given #file_value_callbacks callback.
   *
   * @param string $file_value_callback
   *   The name of the method on this class to use as the callback.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state after submission.
   */
  protected function submitManagedFileForm(string $file_value_callback = 'managedFileValueCallbackTrusted'): FormStateInterface {
    $form_state = new FormState();
    $form_state->setValues(['managed_file' => NULL]);
    \Drupal::formBuilder()->submitForm($this, $form_state, $file_value_callback);
    return $form_state;
  }

  /**
   * Tests that managed file elements can be programmatically submitted.
   */
  public function testManagedFileElement(): void {
    $form_state = $this->submitManagedFileForm();
    // Should submit without any errors.
    $this->assertCount(0, $form_state->getErrors());
    $this->assertTrue($form_state->get('managedFileValueCallbackExecuted'));
  }

  /**
   * Tests that exceptions are raised if untrusted callbacks are used.
   */
  public function testManagedFileElementUntrustedCallbacks(): void {
    $this->expectException(UntrustedCallbackException::class);
    $this->expectExceptionMessage(sprintf(
      'ManagedFile element #file_value_callbacks callbacks must be tagged with the %s attribute, be methods of a class that implements %s, or be an anonymous function. The callback was %s. See https://www.drupal.org/node/3620771',
      TrustedCallback::class,
      TrustedCallbackInterface::class,
      Variable::callableToString([$this, 'managedFileValueCallback'])
    ));
    $this->submitManagedFileForm('managedFileValueCallback');
  }

}
