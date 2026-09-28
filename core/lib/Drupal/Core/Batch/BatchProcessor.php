<?php

namespace Drupal\Core\Batch;

use Drupal\Component\Utility\Timer;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
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
/**
 * Processor for batches.
 */
class BatchProcessor {
  use StringTranslationTrait;

  public function __construct(
    protected BatchStorageInterface $batchStorage,
    protected MessengerInterface $messenger,
    #[Autowire(service: 'cache.memory')]
    protected MemoryCacheInterface $memoryCache,
    protected BareHtmlPageRendererInterface $bareHtmlPageRenderer,
    protected CallableResolver $callableResolver,
    protected DateFormatterInterface $dateFormatter,
    protected RequestStack $requestStack,
    protected PathValidatorInterface $pathValidator,
    protected FormSubmitterInterface $formSubmitter,
    #[Autowire(param: 'app.root')]
    protected string $root,
  ) {}

  /**
   * Renders the batch processing page based on the current state of the batch.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return bool|array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The response for the page triggering the batch.
   *
   * @see self::shutdown()
   */
  public function page(Request $request): bool|array|JsonResponse|RedirectResponse|Response|null {
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

    // We need to store the updated batch information in the batch storage
    // after processing the batch. In order for the error page to work
    // correctly this needs to be done even in case of a PHP fatal error in
    // which case the end of this function is never reached. Therefore we
    // register a shutdown function to handle this case. Because with FastCGI
    // and fastcgi_finish_request() shutdown functions are called after the
    // HTTP connection is closed, updating the batch information in a shutdown
    // function would lead to race conditions between consecutive requests if
    // the batch processing continues. In case of a fatal error the processing
    // stops anyway, so it works even with FastCGI. However, we must ensure to
    // only update in the shutdown phase in this particular case we track
    // whether the batch information still needs to be updated.
    // @see self::shutdown()
    // @see \Symfony\Component\HttpFoundation\Response::send()
    drupal_register_shutdown_function(self::class . '::shutdown');
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
        $currentSet = $this->currentSet();
        $build['#title'] = $currentSet['title'];
        $build['content'] = $this->progressPage();

        $response = $build;
        break;

      case 'do':
        // JavaScript-based progress page callback.
        $response = $this->doBatch();
        break;

      case 'finished':
        $response = $this->finished();
        break;
    }

    if ($batch) {
      $this->batchStorage->update($batch);
    }
    $this->needsUpdate(FALSE);

    return $response;
  }

  /**
   * Processes sets in a batch.
   *
   * If the batch was marked for progressive execution (default), this executes
   * as many operations in batch sets until an execution time of 1 second has
   * been exceeded. It will continue with the next operation of the same batch
   * set in the next request.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse|null
   *   An array containing a completion value (in percent) and a status message.
   */
  public function process(): array|RedirectResponse|null {
    $batch = &batch_get();
    $currentSet = &$this->currentSet();
    // Indicate that this batch set needs to be initialized.
    $setChanged = TRUE;
    $taskMessage = '';

    // If this batch was marked for progressive execution (e.g. forms submitted
    // by FormBuilderInterface::submitForm(), initialize a timer to determine
    // whether we need to proceed with the same batch phase when a processing
    // time of 1 second has been exceeded.
    if ($batch['progressive']) {
      Timer::start('batch_processing');
    }

    if (empty($currentSet['start'])) {
      $currentSet['start'] = microtime(TRUE);
    }

    $queue = _batch_queue($currentSet);

    while (!$currentSet['success']) {
      // If this is the first time we iterate this batch set in the current
      // request, we check if it requires an additional file for functions
      // definitions.
      if ($setChanged && isset($currentSet['file']) && is_file($currentSet['file'])) {
        include_once $this->root . '/' . $currentSet['file'];
      }

      $taskMessage = '';
      // Assume a single pass operation and set the completion level to 1 by
      // default.
      $finished = 1;

      if ($item = $queue->claimItem()) {
        [$callback, $args] = $item->data;

        // Build the 'context' array and execute the function call.
        $batchContext = [
          'sandbox'  => &$currentSet['sandbox'],
          'results'  => &$currentSet['results'],
          'finished' => &$finished,
          'message'  => &$taskMessage,
        ];
        $callable = $this->callableResolver->getCallableFromDefinition($callback);
        $callable(...array_merge($args, [&$batchContext]));

        if ($finished >= 1) {
          // Make sure this step is not counted twice when computing $current.
          $finished = 0;
          // Remove the processed operation and clear the sandbox.
          $queue->deleteItem($item);
          $currentSet['count']--;
          $currentSet['sandbox'] = [];
        }
      }

      // When all operations in the current batch set are completed, browse
      // through the remaining sets, marking them 'successfully processed'
      // along the way, until we find a set that contains operations.
      // self::nextSet() executes form submit handlers stored in 'control'
      // sets (see \Drupal::service('form_submitter')), which can in turn add
      // new sets to the batch.
      $setChanged = FALSE;
      $oldSet = $currentSet;
      while (empty($currentSet['count']) && ($currentSet['success'] = TRUE) && $this->nextSet()) {
        $currentSet = &$this->currentSet();
        $currentSet['start'] = microtime(TRUE);
        $setChanged = TRUE;
      }

      // At this point, either $currentSet contains operations that need to be
      // processed or all sets have been completed.
      $queue = _batch_queue($currentSet);

      // If we are in progressive mode, break processing after 1 second.
      if ($batch['progressive'] && Timer::read('batch_processing') > 1000) {
        // Record elapsed wall clock time.
        $currentSet['elapsed'] = round((microtime(TRUE) - $currentSet['start']) * 1000, 2);
        break;
      }
    }

    if ($batch['progressive']) {
      // Gather progress information.

      // Reporting 100% progress will cause the whole batch to be considered
      // processed. If processing was paused right after moving to a new set,
      // we have to use the info from the new (unprocessed) set.
      if ($setChanged && isset($currentSet['queue'])) {
        // Processing will continue with a fresh batch set.
        $remaining       = $currentSet['count'];
        $total           = $currentSet['total'];
        $progressMessage = $currentSet['init_message'];
        $taskMessage     = '';
      }
      else {
        // Processing will continue with the current batch set.
        $remaining       = $oldSet['count'] ?? 0;
        $total           = $oldSet['total'] ?? 0;
        $progressMessage = $oldSet['progress_message'] ?? '';
      }

      // Total progress is the number of operations that have fully run plus the
      // completion level of the current operation.
      $current    = $total - $remaining + ($finished ?? 0);
      $percentage = Percentage::format($total, $current);
      $elapsed    = $currentSet['elapsed'] ?? 0;
      $values     = [
        '@remaining'  => $remaining,
        '@total'      => $total,
        '@current'    => floor($current),
        '@percentage' => $percentage,
        '@elapsed'    => $this->dateFormatter->formatInterval((int) ($elapsed / 1000)),
        // If possible, estimate remaining processing time.
        '@estimate'   => ($current > 0) ? $this->dateFormatter->formatInterval((int) (($elapsed * ($total - $current) / $current) / 1000)) : '-',
      ];
      $message    = strtr($progressMessage, $values);

      return [$percentage, $message, $taskMessage];
    }
    else {
      // If we are not in progressive mode, the entire batch has been processed.
      return $this->finished();
    }
  }

  /**
   * Returns the batch set being currently processed.
   *
   * @return int|string|array
   *   The current set for the batch.
   */
  public function &currentSet(): int|string|array {
    $batch = &batch_get();
    return $batch['sets'][$batch['current_set']];
  }

  /**
   * Checks whether the batch information needs to be updated in the storage.
   *
   * @param ?bool $newValue
   *   (optional) A new value to set.
   *
   * @return ?bool
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
   * Does one execution pass with JavaScript and returns progress.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JsonResponse.
   *
   * @see self::process()
   */
  protected function doBatch(): JsonResponse {
    // Perform actual processing.
    [$percentage, $message, $label] = $this->process();

    return new JsonResponse(['status' => TRUE, 'percentage' => $percentage, 'message' => $message, 'label' => $label]);
  }

  /**
   * Outputs a batch processing page.
   *
   * @return array
   *   Progress bar render array.
   *
   * @see self::process()
   */
  protected function progressPage(): array {
    $batch = &batch_get();

    $currentSet = $this->currentSet();

    $newOp = 'do_nojs';

    if (!isset($batch['running'])) {
      // This is the first page so we return some output immediately.
      $percentage       = 0;
      $message          = $currentSet['init_message'];
      $label            = '';
      $batch['running'] = TRUE;
    }
    else {
      // This is one of the later requests; do some processing first.

      // Error handling: if PHP dies due to a fatal error (e.g. a nonexistent
      // function), it will output whatever is in the output buffer, followed by
      // the error message.
      ob_start();
      $fallback = $currentSet['error_message'] . '<br />' . $batch['error_message'];

      // We strip the end of the page using a marker in the template, so any
      // additional HTML output by PHP shows up inside the page rather than
      // below it. While this causes invalid HTML, the same would be true if
      // we didn't, as content is not allowed to appear after </html> anyway.
      $response = $this->bareHtmlPageRenderer->renderBarePage(['#markup' => $fallback], $currentSet['title'], 'maintenance_page', [
        '#show_messages' => FALSE,
      ]);

      // Just use the content of the response.
      $fallback = $response->getContent();

      [$fallback] = explode('<!--partial-->', $fallback);
      print $fallback;

      // Perform actual processing.
      [$percentage, $message, $label] = $this->process();
      if ($percentage == 100) {
        $newOp = 'finished';
      }

      // PHP did not die; remove the fallback output.
      ob_end_clean();
    }

    // Merge required query parameters for batch processing into those provided
    // by batch_set() or hook_batch_alter().
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
              // Redirect through a 'Refresh' meta tag if JavaScript is
              // disabled.
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

  /**
   * Retrieves the next set in a batch.
   *
   * If there is a subsequent set in this batch, assign it as the new set to
   * process and execute its form submit handler (if defined), which may add
   * further sets to this batch.
   *
   * @return ?bool
   *   TRUE if a subsequent set was found in the batch; no value will be
   *   returned if no subsequent set was found.
   */
  protected function nextSet(): ?bool {
    $batch = &batch_get();
    $setIndexes = array_keys($batch['sets']);
    $currentSetIndexKey = array_search($batch['current_set'], $setIndexes);
    if (isset($setIndexes[$currentSetIndexKey + 1])) {
      $batch['current_set'] = $setIndexes[$currentSetIndexKey + 1];
      $currentSet = &$this->currentSet();
      if (isset($currentSet['form_submit']) && ($callback = $currentSet['form_submit']) && is_callable($callback)) {
        // We use our stored copies of $form and $form_state to account for
        // possible alterations by previous form submit handlers.
        $completeForm = &$batch['form_state']->getCompleteForm();
        $callable = $this->callableResolver->getCallableFromDefinition($callback);
        $callable(...[&$completeForm, &$batch['form_state']]);
      }
      return TRUE;
    }
    return NULL;
  }

  /**
   * Ends the batch processing.
   *
   * Call the 'finished' callback of each batch set to allow custom handling of
   * the results and resolve page redirection.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   Page to load after the batch finished.
   */
  protected function finished(): ?RedirectResponse {
    $batch = &batch_get();
    $batchFinishedRedirect = NULL;

    // Execute the 'finished' callbacks for each batch set, if defined.
    foreach ($batch['sets'] as $batchSet) {
      if (isset($batchSet['finished'])) {
        // Check if the set requires an additional file for function
        // definitions.
        if (isset($batchSet['file']) && is_file($batchSet['file'])) {
          include_once $this->root . '/' . $batchSet['file'];
        }

        $queue = _batch_queue($batchSet);
        $operations = $queue->getAllItems();
        $callable = $this->callableResolver->getCallableFromDefinition($batchSet['finished']);
        $batchSetResult = $callable(
          $batchSet['success'],
          $batchSet['results'],
          $operations,
          $this->dateFormatter->formatInterval((int) ($batchSet['elapsed'] / 1000)),
        );
        // If a batch 'finished' callback requested a redirect after the batch
        // is complete, save that for later use. If more than one batch set
        // returned a redirect, the last one is used.
        if ($batchSetResult instanceof RedirectResponse) {
          $batchFinishedRedirect = $batchSetResult;
        }
      }
    }

    // Clean up the batch table and unset the static $batch variable.
    if ($batch['progressive']) {
      $this->batchStorage->delete($batch['id']);
      foreach ($batch['sets'] as $batchSet) {
        if ($queue = _batch_queue($batchSet)) {
          $queue->deleteQueue();
        }
      }
      // Clean-up the session. Not needed for CLI updates.
      $session = $this->requestStack->getSession();
      $batches = $session->get('batches', []);
      unset($batches[$batch['id']]);
      if (empty($batches)) {
        $session->remove('batches');
      }
      else {
        $session->set('batches', $batches);
      }
    }
    $_batch = $batch;
    $batch = NULL;

    // Redirect if needed.
    if ($_batch['progressive']) {
      // Revert the 'destination' that was saved in batch_process().
      if (isset($_batch['destination'])) {
        $this->requestStack->getCurrentRequest()->query->set('destination', $_batch['destination']);
      }

      // Determine the target path to redirect to. If a batch 'finished'
      // callback returned a redirect response object, use that. Otherwise,
      // fall back on the form redirection.
      if (isset($batchFinishedRedirect)) {
        return $batchFinishedRedirect;
      }
      elseif (!isset($_batch['form_state'])) {
        $_batch['form_state'] = new FormState();
      }
      if ($_batch['form_state']->getRedirect() === NULL) {
        $redirect = $_batch['batch_redirect'] ?: $_batch['source_url'];
        // Any path with a scheme does not correspond to a route.
        if (!$redirect instanceof Url) {
          $options = UrlHelper::parse($redirect);
          if (parse_url($options['path'], PHP_URL_SCHEME)) {
            $redirect = Url::fromUri($options['path'], $options);
          }
          else {
            $redirect = $this->pathValidator->getUrlIfValid($options['path']);
            if (!$redirect) {
              // Stay on the same page if the redirect was invalid.
              $redirect = Url::fromRoute('<current>');
            }
            $redirect->setOptions($options);
          }
        }
        $_batch['form_state']->setRedirectUrl($redirect);
      }

      // Use \Drupal\Core\Form\FormSubmitterInterface::redirectForm() to handle
      // the redirection logic.
      $redirect = $this->formSubmitter->redirectForm($_batch['form_state']);
      if (is_object($redirect)) {
        return $redirect;
      }

      // If no redirection happened, redirect to the originating page. In case
      // the form needs to be rebuilt, save the final $form_state for
      // \Drupal\Core\Form\FormBuilderInterface::buildForm().
      if ($_batch['form_state']->isRebuilding()) {
        $session = $this->requestStack->getSession();
        $session->set('batch_form_state', $_batch['form_state']);
      }
      $callback = $_batch['redirect_callback'];
      $_batch['source_url']->mergeOptions(['query' => ['op' => 'finish', 'id' => $_batch['id']]]);
      if (is_callable($callback)) {
        $callback($_batch['source_url'], $_batch['source_url']->getOption('query'));
      }
      elseif ($callback === NULL) {
        // Default to RedirectResponse objects when nothing specified.
        return new RedirectResponse($_batch['source_url']->setAbsolute()->toString());
      }
    }
    return NULL;
  }

  /**
   * Shutdown function: Stores the current batch data for the next request.
   *
   * @see self::page()
   * @see drupal_register_shutdown_function()
   */
  public static function shutdown(): void {
    $cid = 'batch_needs_update';
    $cached = \Drupal::service('cache.memory')->get($cid);
    $needsUpdate = $cached->data ?? NULL;
    if (($batch = batch_get()) && $needsUpdate) {
      \Drupal::service('batch.storage')->update($batch);
    }
  }

}
