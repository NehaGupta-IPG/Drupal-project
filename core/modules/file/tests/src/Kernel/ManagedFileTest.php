<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Managed file element test.
 *
 * @group file
 *
 * @see \Drupal\file\Element\ManagedFile
 */
class ManagedFileTest extends FileManagedUnitTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_managed_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['managed_file'] = [
      '#type' => 'managed_file',
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
   * Tests that managed file elements can be programmatically submitted.
   */
  public function testManagedFileElement(): void {
    $form_state = new FormState();
    $values['managed_file'] = NULL;
    $form_state->setValues($values);
    $form_builder = $this->container->get(FormBuilderInterface::class);
    $form_builder->submitForm($this, $form_state);
    // Should submit without any errors.
    $this->assertEquals(0, count($form_state->getErrors()));

    // Now submit with some existing values.
    // Write a value.
    $filename = $this->randomMachineName() . '.txt';

    $result = $this->container->get(FileRepositoryInterface::class)->writeData($this->getRandomGenerator()->sentences(10), 'public://' . $filename);
    // Mimic what the browser sends, where the fids are imploded by
    // \Drupal\Core\Template\AttributeArray::__toString.
    // @see \Drupal\Core\Template\AttributeArray::__toString
    // @see \Drupal\file\Element\ManagedFile::processManagedFile
    $values['managed_file'] = ['fids' => \implode(' ', [$result->id()])];
    $form_state->setValues($values);
    $form_builder->submitForm($this, $form_state);
    // Even though we submitted a string, the resultant value is an array.
    $submitted_value = $form_state->getValue(['managed_file']);
    self::assertIsArray($submitted_value);
    self::assertEquals([$result->id()], $submitted_value);

    // Now mimic a programmatic submission sending back directly the values
    // built by the form builder.
    $values['managed_file'] = ['fids' => $submitted_value];
    $form_state->setValues($values);
    $form_builder->submitForm($this, $form_state);
  }

}
