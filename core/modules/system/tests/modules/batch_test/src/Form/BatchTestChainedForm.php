<?php

declare(strict_types=1);

namespace Drupal\batch_test\Form;

use Drupal\batch_test\BatchTestDefinitions;
use Drupal\batch_test\BatchTestHelper;
use Drupal\Core\Batch\BatchProcessorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generate form of id batch_test_chained_form.
 *
 * @internal
 */
class BatchTestChainedForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(BatchProcessorInterface::class),
    );
  }

  /**
   * Constructs a new BatchTestChainedForm object.
   *
   * @param \Drupal\Core\Batch\BatchProcessorInterface $batchProcessor
   *   The batch processor.
   */
  public function __construct(
    protected readonly BatchProcessorInterface $batchProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'batch_test_chained_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // This value is used to test that $form_state persists through batched
    // submit handlers.
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => 'Value',
      '#default_value' => 1,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];
    $form['#submit'] = [
      [$this, 'batchTestChainedFormSubmit1'],
      [$this, 'batchTestChainedFormSubmit2'],
      [$this, 'batchTestChainedFormSubmit3'],
      [$this, 'batchTestChainedFormSubmit4'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Form submission handler #1 for batch_test_chained_form.
   */
  public function batchTestChainedFormSubmit1($form, FormStateInterface $form_state) {
    $batch_test_definitions = new BatchTestDefinitions();
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack(NULL, TRUE);
    $batch_test_helper->stack('submit handler 1');
    $batch_test_helper->stack('value = ' . $form_state->getValue('value'));

    $value = &$form_state->getValue('value');
    $value++;
    $this->batchProcessor->queue($batch_test_definitions->batch1());

    $form_state->setRedirect('batch_test.redirect');
  }

  /**
   * Form submission handler #2 for batch_test_chained_form.
   */
  public function batchTestChainedFormSubmit2($form, FormStateInterface $form_state) {
    $batch_test_definitions = new BatchTestDefinitions();
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack('submit handler 2');
    $batch_test_helper->stack('value = ' . $form_state->getValue('value'));

    $value = &$form_state->getValue('value');
    $value++;
    $this->batchProcessor->queue($batch_test_definitions->batch2());

    $form_state->setRedirect('batch_test.redirect');
  }

  /**
   * Form submission handler #3 for batch_test_chained_form.
   */
  public function batchTestChainedFormSubmit3($form, FormStateInterface $form_state) {
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack('submit handler 3');
    $batch_test_helper->stack('value = ' . $form_state->getValue('value'));

    $value = &$form_state->getValue('value');
    $value++;

    $form_state->setRedirect('batch_test.redirect');
  }

  /**
   * Form submission handler #4 for batch_test_chained_form.
   */
  public function batchTestChainedFormSubmit4($form, FormStateInterface $form_state) {
    $batch_test_definitions = new BatchTestDefinitions();
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack('submit handler 4');
    $batch_test_helper->stack('value = ' . $form_state->getValue('value'));

    $value = &$form_state->getValue('value');
    $value++;
    $this->batchProcessor->queue($batch_test_definitions->batch3());

    $form_state->setRedirect('batch_test.redirect');
  }

}
