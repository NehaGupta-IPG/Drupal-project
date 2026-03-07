<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests details element rendering.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class ElementsDetailsTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_details_element';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['details_required'] = [
      '#type' => 'details',
      '#required' => TRUE,
      '#title' => 'Details title for required marker display',
      '#description' => 'Details description for required marker display.',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Tests markup for required details elements.
   */
  public function testDetailsRequired(): void {
    $form = \Drupal::formBuilder()->getForm($this);
    $this->render($form);

    // Verify that summary element contains a "(required)" text.
    $elements = $this->xpath('//details[@id="edit-details-required"]//summary[contains(@class,"js-form-required")]//span[@aria-hidden="true" and @class="hidden" and contains(text(), "(required)")]');
    $this->assertCount(1, $elements);
  }

}
