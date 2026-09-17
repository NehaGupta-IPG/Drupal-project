<?php

declare(strict_types=1);

namespace Drupal\batch_test\Controller;

use Drupal\batch_test\BatchInjectionCallbacks;
use Drupal\batch_test\BatchTestCallbacks;
use Drupal\batch_test\BatchTestDefinitions;
use Drupal\batch_test\BatchTestHelper;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Batch\BatchManagerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormState;

/**
 * Controller routines for batch tests.
 */
class BatchTestController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected readonly BatchManagerInterface $batchManager,
  ) {}

  /**
   * Redirects successfully.
   *
   * @return array
   *   Render array containing success message.
   */
  public function testRedirect() {
    return [
      'success' => [
        '#markup' => 'Redirection successful.',
      ],
    ];
  }

  /**
   * Fires a batch process without a form submission.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive. No return value
   *   otherwise.
   */
  public function testLargePercentage() {
    $batch_test_definitions = new BatchTestDefinitions();
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack(NULL, TRUE);

    $this->batchManager->addBatch($batch_test_definitions->batch5());
    return $this->batchManager->process('batch-test/redirect');
  }

  /**
   * Submits a form within a batch programmatically.
   *
   * @param int $value
   *   Some value passed to a custom batch callback.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive. No return value
   *   otherwise.
   */
  public function testNestedDrupalFormSubmit($value = 1) {
    $batch_test_helper = new BatchTestHelper();
    // Set the batch and process it.
    $batch_builder = (new BatchBuilder())
      ->addOperation([$batch_test_helper, 'nestedDrupalFormSubmitCallback'], [$value]);
    $this->batchManager->addBatch($batch_builder);
    return $this->batchManager->process('batch-test/redirect');
  }

  /**
   * Fires a batch process without a form submission.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive. No return value
   *   otherwise.
   */
  public function testNoForm() {
    $batch_test_definitions = new BatchTestDefinitions();
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack(NULL, TRUE);
    $this->batchManager->addBatch($batch_test_definitions->batch1());
    return $this->batchManager->process('batch-test/redirect');
  }

  /**
   * Fires a batch process without a form submission and a finish redirect.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive. No return value
   *   otherwise.
   */
  public function testFinishRedirect() {
    $batch_test_definitions = new BatchTestDefinitions();
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack(NULL, TRUE);
    $batch = $batch_test_definitions->batch1();
    $batch['finished'] = BatchTestCallbacks::class . ':finished1Finished';
    $this->batchManager->addBatch($batch);
    return $this->batchManager->process('batch-test/redirect');
  }

  /**
   * Submits the 'Chained' form programmatically.
   *
   * Programmatic form: the page submits the 'Chained' form through
   * \Drupal::formBuilder()->submitForm().
   *
   * @param int $value
   *   Some value passed to a the chained form.
   *
   * @return array
   *   Render array containing markup.
   */
  public function testProgrammatic($value = 1) {
    $form_state = (new FormState())->setValues([
      'value' => $value,
    ]);
    \Drupal::formBuilder()->submitForm('Drupal\batch_test\Form\BatchTestChainedForm', $form_state);
    return [
      'success' => [
        '#markup' => 'Got out of a programmatic batched form.',
      ],
    ];
  }

  /**
   * Runs a batch for testing theme used on the progress page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive. No return value
   *   otherwise.
   */
  public function testThemeBatch() {
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack(NULL, TRUE);
    $batch = [
      'operations' => [[BatchInjectionCallbacks::class . ':themeCallback', []]],
    ];
    $this->batchManager->addBatch($batch);
    return $this->batchManager->process('batch-test/redirect');
  }

  /**
   * Runs a batch for testing the title shown on the progress page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive. No return value
   *   otherwise.
   */
  public function testTitleBatch() {
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack(NULL, TRUE);
    $batch = [
      'title' => 'Batch Test',
      'operations' => [[BatchInjectionCallbacks::class . ':titleCallback', []]],
    ];
    $this->batchManager->addBatch($batch);
    return $this->batchManager->process('batch-test/redirect');
  }

}
