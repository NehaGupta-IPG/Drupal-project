<?php

namespace Drupal\update\Controller;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\update\UpdateCalculator;
use Drupal\update\UpdateFetcherInterface;
use Drupal\update\UpdateManagerInterface;

/**
 * Controller routines for update routes.
 */
class UpdateController extends ControllerBase {

  public function __construct(
    protected UpdateManagerInterface $updateManager,
    protected RendererInterface $renderer,
    protected ?UpdateCalculator $updateCalculator = NULL,
  ) {
    if (!$updateCalculator) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $updateCalculator argument is deprecated in drupal:11.5.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3587768', E_USER_DEPRECATED);
      $this->updateCalculator = \Drupal::service(UpdateCalculator::class);
    }
  }

  /**
   * Returns a page about the update status of projects.
   *
   * @return array
   *   A build array with the update status of projects.
   */
  public function updateStatus() {
    $build = [
      '#theme' => 'update_report',
    ];
    if ($available = $this->updateManager->getAvailable(TRUE)) {
      $this->moduleHandler()->loadInclude('update', 'compare.inc');
      $build['#data'] = $this->updateCalculator->getProjectData($available);

      // @todo Consider using 'fetch_failures' from the 'update' collection
      // in the key_value_expire service for this?
      $fetch_failed = FALSE;
      foreach ($build['#data'] as $project) {
        if ($project['status'] === UpdateFetcherInterface::NOT_FETCHED) {
          $fetch_failed = TRUE;
          break;
        }
      }
      if ($fetch_failed) {
        $message = ['#theme' => 'update_fetch_error_message'];
        $this->messenger()->addError($this->renderer->renderInIsolation($message));
      }
    }
    return $build;
  }

  /**
   * Manually checks the update status without the use of cron.
   */
  public function updateStatusManually() {
    $this->updateManager->refreshUpdateData();
    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Checking available update data'))
      ->addOperation([$this->updateManager, 'fetchDataBatch'], [])
      ->setProgressMessage($this->t('Trying to check available update data ...'))
      ->setErrorMessage($this->t('Error checking available update data.'))
      ->setFinishCallback(self::class . ':updateFetchDataFinished');
    batch_set($batch_builder->toArray());
    return batch_process('admin/reports/updates');
  }

  /**
   * Finished batch callback.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   */
  public function updateFetchDataFinished(bool $success, array $results): void {
    if ($success) {
      if (!empty($results)) {
        if (!empty($results['updated'])) {
          $this->messenger()->addStatus($this->formatPlural($results['updated'], 'Checked available update data for one project.', 'Checked available update data for @count projects.'));
        }
        if (!empty($results['failures'])) {
          $this->messenger()->addError($this->formatPlural($results['failures'], 'Failed to get available update data for one project.', 'Failed to get available update data for @count projects.'));
        }
      }
    }
    else {
      $this->messenger()->addError($this->t('An error occurred trying to get available update data.'), 'error');
    }
  }

}
