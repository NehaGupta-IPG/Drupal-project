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

    $form = $form_builder->getForm(FormTestFormIdEqualsBaseFormIdForm::class);
    $this->assertSame([
      FormTestHooks::class . '::formAlter',
      FormTestHooks::class . '::formTestFormIdEqualsBaseFormIdFormAlter',
    ], $form['#alter_calls'] ?? NULL);
  }

}
