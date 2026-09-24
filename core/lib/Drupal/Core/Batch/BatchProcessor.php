<?php

namespace Drupal\Core\Batch;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @file
 * Batch processing API for processes to run in multiple HTTP requests.
 *
 * Note that batches are usually invoked by form submissions, which is
 * why the core interaction functions of the batch processing API live in
 * form.inc.
 *
 * @see form.inc
 * @see batch_set()
 * @see batch_process()
 * @see batch_get()
 *
 * @internal
 */
class BatchProcessor {
  use StringTranslationTrait;

  public function __construct(
    protected BatchStorageInterface $batchStorage,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Renders the batch processing page based on the current state of the batch.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return bool|array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @see _batch_shutdown()
   */
  public function batchPage(Request $request): bool|array|JsonResponse|RedirectResponse {
    $batch = &batch_get();

    if (!($request_id = $request->query->get('id'))) {
      return FALSE;
    }

    // Retrieve the current state of the batch.
    if (!$batch) {
      $batch = $this->batchStorage->load($request_id);
      if (!$batch) {
        $this->messenger->addError($this->t('No batch with ID @batch exists.', ['@batch' => $request_id]));
        throw new NotFoundHttpException(sprintf('No batch with ID %s exists.', $request_id));
      }
    }

    // We need to store the updated batch information in the batch storage after
    // processing the batch. In order for the error page to work correctly this
    // needs to be done even in case of a PHP fatal error in which case the end of
    // this function is never reached. Therefore we register a shutdown function
    // to handle this case. Because with FastCGI and fastcgi_finish_request()
    // shutdown functions are called after the HTTP connection is closed, updating
    // the batch information in a shutdown function would lead to race conditions
    // between consecutive requests if the batch processing continues. In case of
    // a fatal error the processing stops anyway, so it works even with FastCGI.
    // However, we must ensure to only update in the shutdown phase in this
    // particular case we track whether the batch information still needs to be
    // updated.
    // @see _batch_shutdown()
    // @see \Symfony\Component\HttpFoundation\Response::send()
    drupal_register_shutdown_function('_batch_shutdown');
    _batch_needs_update(TRUE);

    $build = [];

    // Add batch-specific libraries.
    foreach ($batch['sets'] as $batch_set) {
      if (isset($batch_set['library'])) {
        foreach ($batch_set['library'] as $library) {
          $build['#attached']['library'][] = $library;
        }
      }
    }

    $response = FALSE;
    $op = $request->query->get('op', '');
    switch ($op) {
      case 'start':
      case 'do_nojs':
        // Display the full progress page on startup and on each additional
        // non-JavaScript iteration.
        $current_set = _batch_current_set();
        $build['#title'] = $current_set['title'];
        $build['content'] = _batch_progress_page();

        $response = $build;
        break;

      case 'do':
        // JavaScript-based progress page callback.
        $response = _batch_do();
        break;

      case 'finished':
        // _batch_finished() returns a RedirectResponse.
        $response = _batch_finished();
        break;
    }

    if ($batch) {
      $this->batchStorage->update($batch);
    }
    _batch_needs_update(FALSE);

    return $response;
  }

}
