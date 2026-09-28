<?php

namespace Drupal\Core\Batch;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    #[Autowire(service: 'cache.memory')]
    protected MemoryCacheInterface $memoryCache,
    protected BareHtmlPageRendererInterface $bareHtmlPageRenderer
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
  public function page(Request $request): bool|array|JsonResponse|RedirectResponse {
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
    $this->needsUpdate(TRUE);

    $build = [];

    // Add batch-specific libraries.
    foreach ($batch['sets'] as $batchSet) {
      if (isset($batchSet['library'])) {
        foreach ($batchSet['library'] as $library) {
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
        $currentSet = _batch_current_set();
        $build['#title'] = $currentSet['title'];
        $build['content'] = $this->progressPage();

        $response = $build;
        break;

      case 'do':
        // JavaScript-based progress page callback.
        $response = $this->doBatch();
        break;

      case 'finished':
        // _batch_finished() returns a RedirectResponse.
        $response = _batch_finished();
        break;
    }

    if ($batch) {
      $this->batchStorage->update($batch);
    }
    $this->needsUpdate(FALSE);

    return $response;
  }

  /**
   * Checks whether the batch information needs to be updated in the storage.
   *
   * @param ?bool $newValue
   *   (optional) A new value to set.
   *
   * @return bool
   *   TRUE if the batch information needs to be updated; FALSE otherwise.
   */
  protected function needsUpdate(?bool $newValue = NULL): ?bool {
    $cid = 'batch_needs_update';
    $cached = $this->memoryCache->get($cid);
    $needsUpdate = $cached->data ?? NULL;

    if (isset($newValue)) {
      $needsUpdate = $newValue;
      $this->memoryCache->set($cid, $needsUpdate);
    }

    return $needsUpdate;
  }

  /**
   * Does one execution pass with JavaScript and returns progress to the browser.
   *
   * @see _batch_process()
   */
  protected function doBatch(): JsonResponse {
    // Perform actual processing.
    [$percentage, $message, $label] = _batch_process();

    return new JsonResponse(['status' => TRUE, 'percentage' => $percentage, 'message' => $message, 'label' => $label]);
  }

  /**
   * Outputs a batch processing page.
   *
   * @see _batch_process()
   */
  protected function progressPage(): array {
    $batch = &batch_get();

    $currentSet = _batch_current_set();

    $newOp = 'do_nojs';

    if (!isset($batch['running'])) {
      // This is the first page so we return some output immediately.
      $percentage       = 0;
      $message          = $currentSet['init_message'];
      $label            = '';
      $batch['running'] = TRUE;
    } else {
      // This is one of the later requests; do some processing first.

      // Error handling: if PHP dies due to a fatal error (e.g. a nonexistent
      // function), it will output whatever is in the output buffer, followed by
      // the error message.
      ob_start();
      $fallback = $currentSet['error_message'] . '<br />' . $batch['error_message'];

      // We strip the end of the page using a marker in the template, so any
      // additional HTML output by PHP shows up inside the page rather than below
      // it. While this causes invalid HTML, the same would be true if we didn't,
      // as content is not allowed to appear after </html> anyway.
      $response = $this->bareHtmlPageRenderer->renderBarePage(['#markup' => $fallback], $currentSet['title'], 'maintenance_page', [
        '#show_messages' => FALSE,
      ]);

      // Just use the content of the response.
      $fallback = $response->getContent();

      [$fallback] = explode('<!--partial-->', $fallback);
      print $fallback;

      // Perform actual processing.
      [$percentage, $message, $label] = _batch_process();
      if ($percentage == 100) {
        $newOp = 'finished';
      }

      // PHP did not die; remove the fallback output.
      ob_end_clean();
    }

    // Merge required query parameters for batch processing into those provided by
    // batch_set() or hook_batch_alter().
    $queryOptions = $batch['url']->getOption('query');
    $queryOptions['id'] = $batch['id'];
    $queryOptions['op'] = $newOp;
    $batch['url']->setOption('query', $queryOptions);

    $url = $batch['url']->toString(TRUE)->getGeneratedUrl();

    $build = [
      '#theme' => 'progress_bar',
      '#percent' => $percentage,
      '#message' => ['#markup' => $message],
      '#label' => $label,
      '#attached' => [
        'html_head' => [
          [
            [
              // Redirect through a 'Refresh' meta tag if JavaScript is disabled.
              '#tag' => 'meta',
              '#noscript' => TRUE,
              '#attributes' => [
                'http-equiv' => 'Refresh',
                'content' => '0; URL=' . $url,
              ],
            ],
            'batch_progress_meta_refresh',
          ],
        ],
        // Adds JavaScript code and settings for clients where JavaScript is
        // enabled.
        'drupalSettings' => [
          'batch' => [
            'errorMessage' => $currentSet['error_message'] . '<br />' . $batch['error_message'],
            'initMessage' => $currentSet['init_message'],
            'uri' => $url,
          ],
        ],
        'library' => [
          'core/drupal.batch',
        ],
      ],
    ];
    return $build;
  }

}
