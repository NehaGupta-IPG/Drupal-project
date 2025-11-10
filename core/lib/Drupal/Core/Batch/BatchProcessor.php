<?php

declare(strict_types=1);

namespace Drupal\Core\Batch;

use Drupal\Component\Utility\Timer;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Implements the default batch processor.
 *
 * @internal
 */
class BatchProcessor implements BatchProcessorInterface {

  use StringTranslationTrait;

  /**
   * In memory batch cache.
   *
   * Not drupal_static(), because Batch API operates at a lower level than most
   * use-cases for resetting static variables, and we specifically do not want
   * a global drupal_static_reset() resetting the batch information. Functions
   * that are part of the Batch API and need to reset the batch information may
   * call BatchProcessorInterface::getCurrentBatch() and manipulate the result
   * by reference. Functions that are not part of the Batch API can also do
   * this, but shouldn't.
   *
   * @var array|null|false
   */
  protected static null|array|false $batch = [];

  /**
   * Queue list storage.
   *
   * @var \Drupal\Core\Queue\QueueInterface[]
   */
  protected static array $queues = [];

  /**
   * Checks whether the batch information needs to be updated in the storage.
   *
   * @var bool
   */
  protected static bool $needsUpdate = FALSE;

  /**
   * Creates a new BatchProcessor.
   *
   * @param string $root
   *   The app root.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Closure $connection
   *   The database connection.
   * @param \Closure $batchStorage
   *   The batch storage.
   * @param \Closure $pathValidator
   *   The path validator.
   * @param \Closure $formSubmitter
   *   The form submitter.
   * @param \Closure $callableResolver
   *   The callable resolver.
   */
  public function __construct(
    protected readonly string $root,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly RequestStack $requestStack,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly \Closure $connection,
    protected readonly \Closure $batchStorage,
    protected readonly \Closure $pathValidator,
    protected readonly \Closure $formSubmitter,
    protected readonly \Closure $callableResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function queue(array $batch_definition): void {
    if ($batch_definition) {
      // Initialize the batch if needed.
      if (empty(static::$batch)) {
        static::$batch = [
          'sets' => [],
          'has_form_submits' => FALSE,
        ];
      }

      // Base and default properties for the batch set.
      $init = [
        'sandbox' => [],
        'results' => [],
        'success' => FALSE,
        'start' => 0,
        'elapsed' => 0,
      ];
      $defaults = [
        'title' => $this->t('Processing'),
        'init_message' => $this->t('Initializing.'),
        'progress_message' => $this->t('Completed @current of @total.'),
        'error_message' => $this->t('An error has occurred.'),
      ];
      $batch_set = $init + $batch_definition + $defaults;

      // Tweak init_message to avoid the bottom of the page flickering down
      // after init phase.
      $batch_set['init_message'] .= '<br/>&nbsp;';

      // The non-concurrent workflow of batch execution allows us to save
      // numberOfItems() queries by handling our own counter.
      $batch_set['total'] = count($batch_set['operations']);
      $batch_set['count'] = $batch_set['total'];

      // Add the set to the batch.
      if (empty(static::$batch['id'])) {
        // The batch is not running yet. Simply add the new set.
        static::$batch['sets'][] = $batch_set;
      }
      else {
        // The set is being added while the batch is running.
        $this->appendSet(static::$batch, $batch_set);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function appendSet(array &$batch, array $batch_set): void {
    $append_after_index = $batch['current_set'];
    $reached_current_set = FALSE;
    foreach ($batch['sets'] as $index => $set) {
      // As the indexes are not ordered numerically we need to first reach the
      // index of the current set and then search for the proper place to
      // append the new batch set.
      if (!$reached_current_set) {
        if ($index == $batch['current_set']) {
          $reached_current_set = TRUE;
        }
        continue;
      }
      if ($index > $append_after_index) {
        if (isset($set['appended_after_index'])) {
          $append_after_index = $index;
        }
        else {
          break;
        }
      }
    }
    $batch_set['appended_after_index'] = $append_after_index;

    // Iterate by reference over the existing batch sets and assign them by
    // reference in the new batch sets array in order not to break a retrieved
    // reference to the current set. Among other places a reference to the
    // current set is being retrieved in
    // \Drupal\Core\Batch\BatchProcessorInterface::process(). Additionally,
    // we have to preserve the original indexes, as they are used to generate
    // the queue name of each batch set, otherwise the operations of the new
    // batch set will be queued in the queue of a previous batch set.
    // @see \Drupal\Core\Batch\BatchProcessorInterface::queuePopulate().
    $new_sets = [];
    $new_set_index = NULL;
    foreach ($batch['sets'] as $index => &$set) {
      $new_sets[$index] = &$set;
      if ($index == $append_after_index) {
        $new_set_index = count($batch['sets']);
        $new_sets[$new_set_index] = $batch_set;
      }
    }

    $batch['sets'] = $new_sets;
    $this->queuePopulate($batch, $new_set_index);
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function queuePopulate(array &$batch, int $set_id): void {
    $batch_set = &$batch['sets'][$set_id];

    if (isset($batch_set['operations'])) {
      $batch_set += [
        'queue' => [
          'name' => 'drupal_batch:' . $batch['id'] . ':' . $set_id,
          'class' => $batch['progressive'] ? 'Drupal\Core\Queue\Batch' : 'Drupal\Core\Queue\BatchMemory',
        ],
      ];

      $queue = $this->getQueue($batch_set);
      $queue->createQueue();
      foreach ($batch_set['operations'] as $operation) {
        $queue->createItem($operation);
      }

      unset($batch_set['operations']);
    }
  }

  /**
   * Gets the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected function getConnection(): Connection {
    return ($this->connection)();
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function getQueue(array $batch_set): ?QueueInterface {
    if (!isset($batch_set['queue'])) {
      return NULL;
    }
    $name = $batch_set['queue']['name'];
    $class = $batch_set['queue']['class'];

    if (!isset(static::$queues[$class][$name])) {
      static::$queues[$class][$name] = new $class($name, $this->getConnection());
    }
    return static::$queues[$class][$name];

  }

  /**
   * {@inheritdoc}
   */
  public function process(Url|string|null $redirect = NULL, ?Url $url = NULL, ?string $redirect_callback = NULL): ?RedirectResponse {

    if (!empty(static::$batch)) {
      // Add process information.
      $process_info = [
        'current_set' => 0,
        'progressive' => TRUE,
        'url' => $url ?? Url::fromRoute('system.batch_page.html'),
        'source_url' => Url::fromRouteMatch($this->routeMatch)->mergeOptions(['query' => $this->requestStack->getCurrentRequest()->query->all()]),
        'batch_redirect' => $redirect,
        'theme' => $this->themeManager->getActiveTheme()->getName(),
        'redirect_callback' => $redirect_callback,
      ];
      static::$batch += $process_info;

      // The batch is now completely built. Allow other modules to make changes
      // to the batch so that it is easier to reuse batch processes in other
      // environments.
      $this->moduleHandler->alter('batch', static::$batch);

      // Assign an id to progressive batches. Non-progressive batches skip
      // database storage completely.
      static::$batch['id'] = static::$batch['progressive'] ? $this->getBatchStorage()->getId() : 'non-progressive';

      // Move operations to a job queue. Non-progressive batches will use a
      // memory-based queue.
      foreach (static::$batch['sets'] as $key => $batch_set) {
        $this->queuePopulate(static::$batch, $key);
      }

      // Initiate processing.
      if (static::$batch['progressive']) {
        // Now that we have a batch id, we can generate the redirection link in
        // the generic error message.
        /** @var \Drupal\Core\Url $batch_url */
        $batch_url = static::$batch['url'];
        /** @var \Drupal\Core\Url $error_url */
        $error_url = clone $batch_url;
        $query_options = $error_url->getOption('query');
        $query_options['id'] = static::$batch['id'];
        $query_options['op'] = 'finished';
        $error_url->setOption('query', $query_options);

        static::$batch['error_message'] = $this->t('Continue to <a href=":error_url">the error page</a>', [':error_url' => $error_url->toString(TRUE)->getGeneratedUrl()]);

        // Clear the way for the redirection to the batch processing page, by
        // saving and unsetting the 'destination', if there is any.
        $request = $this->requestStack->getCurrentRequest();
        if ($request->query->has('destination')) {
          static::$batch['destination'] = $request->query->get('destination');
          $request->query->remove('destination');
        }

        // Store the batch.
        $this->getBatchStorage()->create(static::$batch);

        // Set the batch number in the session to guarantee that it will stay
        // alive.
        $session = $this->requestStack->getSession();
        $batches = $session->get('batches', []);
        $batches[static::$batch['id']] = TRUE;
        $session->set('batches', $batches);

        // Redirect for processing.
        $query_options = $error_url->getOption('query');
        $query_options['op'] = 'start';
        $query_options['id'] = static::$batch['id'];
        $batch_url->setOption('query', $query_options);
        if (($callback = static::$batch['redirect_callback'])) {
          $callable = $this->getCallableResolver()->getCallableFromDefinition($callback);
          $callable($batch_url->toString(), ['query' => $query_options]);
        }
        else {
          return new RedirectResponse($batch_url->setAbsolute()->toString(TRUE)->getGeneratedUrl());
        }
      }
      else {
        // Non-progressive execution: bypass the whole progressbar workflow.
        $this->processQueue();
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function &getCurrentBatch(): null|array|false {
    return static::$batch;
  }

  /**
   * {@inheritdoc}
   */
  public function processQueue(): array|NULL|RedirectResponse|JsonResponse {
    $current_set = &$this->getCurrentSet();
    // Indicate that this batch set needs to be initialized.
    $set_changed = TRUE;
    $task_message = '';

    // If this batch was marked for progressive execution (e.g. forms submitted
    // by \Drupal::formBuilder()->submitForm(), initialize a timer to determine
    // whether we need to proceed with the same batch phase when a processing
    // time of 1 second has been exceeded.
    if (static::$batch['progressive']) {
      Timer::start('batch_processing');
    }

    if (empty($current_set['start'])) {
      $current_set['start'] = microtime(TRUE);
    }

    $queue = $this->getQueue($current_set);

    while (!$current_set['success']) {
      // If this is the first time we iterate this batch set in the current
      // request, we check if it requires an additional file for functions
      // definitions.
      if ($set_changed && isset($current_set['file']) && is_file($current_set['file'])) {
        include_once $this->root . '/' . $current_set['file'];
      }

      $task_message = '';
      // Assume a single pass operation and set the completion level to 1 by
      // default.
      $finished = 1;

      if ($item = $queue->claimItem()) {
        [$callback, $args] = $item->data;

        // Build the 'context' array and execute the function call.
        $batch_context = [
          'sandbox'  => &$current_set['sandbox'],
          'results'  => &$current_set['results'],
          'finished' => &$finished,
          'message'  => &$task_message,
        ];
        $callable = $this->getCallableResolver()->getCallableFromDefinition($callback);
        $callable(...array_merge($args, [&$batch_context]));

        if ($finished >= 1) {
          // Make sure this step is not counted twice when computing $current.
          $finished = 0;
          // Remove the processed operation and clear the sandbox.
          $queue->deleteItem($item);
          $current_set['count']--;
          $current_set['sandbox'] = [];
        }
      }

      // When all operations in the current batch set are completed, browse
      // through the remaining sets, marking them 'successfully processed'
      // along the way, until we find a set that contains operations.
      // \Drupal\Core\Batch\BatchProcessorInterface::nextSet() executes form
      // submit handlers stored in 'control' sets
      // (see \Drupal::service('form_submitter')), which can in turn add new
      // sets to the batch.
      $set_changed = FALSE;
      $old_set = $current_set;
      while (empty($current_set['count']) && ($current_set['success'] = TRUE) && $this->nextSet()) {
        $current_set = &$this->getCurrentSet();
        $current_set['start'] = microtime(TRUE);
        $set_changed = TRUE;
      }

      // At this point, either $current_set contains operations that need to be
      // processed or all sets have been completed.
      $queue = $this->getQueue($current_set);

      // If we are in progressive mode, break processing after 1 second.
      if (static::$batch['progressive'] && Timer::read('batch_processing') > 1000) {
        // Record elapsed wall clock time.
        $current_set['elapsed'] = round((microtime(TRUE) - $current_set['start']) * 1000, 2);
        break;
      }
    }

    if (static::$batch['progressive']) {
      // Gather progress information.

      // Reporting 100% progress will cause the whole batch to be considered
      // processed. If processing was paused right after moving to a new set,
      // we have to use the info from the new (unprocessed) set.
      if ($set_changed && isset($current_set['queue'])) {
        // Processing will continue with a fresh batch set.
        $remaining        = $current_set['count'];
        $total            = $current_set['total'];
        $progress_message = $current_set['init_message'];
        $task_message     = '';
      }
      else {
        // Processing will continue with the current batch set.
        $remaining        = $old_set['count'] ?? 0;
        $total            = $old_set['total'] ?? 0;
        $progress_message = $old_set['progress_message'] ?? '';
      }

      // Total progress is the number of operations that have fully run plus
      // the completion level of the current operation.
      $current    = $total - $remaining + ($finished ?? 0);
      $percentage = Percentage::format($total, $current);
      $elapsed    = $current_set['elapsed'] ?? 0;
      $values     = [
        '@remaining'  => $remaining,
        '@total'      => $total,
        '@current'    => floor($current),
        '@percentage' => $percentage,
        '@elapsed'    => $this->dateFormatter->formatInterval((int) ($elapsed / 1000)),
        // If possible, estimate remaining processing time.
        '@estimate'   => ($current > 0) ? $this->dateFormatter->formatInterval((int) (($elapsed * ($total - $current) / $current) / 1000)) : '-',
      ];
      $message    = strtr((string) $progress_message, $values);

      return [$percentage, $message, $task_message];
    }
    // If we are not in progressive mode, the entire batch has been processed.
    return $this->finishedProcessing();
  }

  /**
   * {@inheritdoc}
   */
  public function &getCurrentSet(): array {
    return static::$batch['sets'][static::$batch['current_set']];
  }

  /**
   * {@inheritdoc}
   */
  public function nextSet(): bool {
    $set_indexes = array_keys(static::$batch['sets']);
    $current_set_index_key = array_search(static::$batch['current_set'], $set_indexes, TRUE);
    if (isset($set_indexes[$current_set_index_key + 1])) {
      static::$batch['current_set'] = $set_indexes[$current_set_index_key + 1];
      $current_set = &$this->getCurrentSet();
      if (isset($current_set['form_submit']) && ($callback = $current_set['form_submit']) && is_callable($callback)) {
        // We use our stored copies of $form and $form_state to account for
        // possible alterations by previous form submit handlers.
        $complete_form = &static::$batch['form_state']->getCompleteForm();
        $callable = $this->getCallableResolver()->getCallableFromDefinition($callback);
        $callable(...[&$complete_form, static::$batch['form_state']]);
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Form\FormSubmitter::submitForm()
   */
  public function finishedProcessing(): ?RedirectResponse {
    $batch_finished_redirect = NULL;

    // Execute the 'finished' callbacks for each batch set, if defined.
    foreach (static::$batch['sets'] as $batch_set) {
      if (isset($batch_set['finished'])) {
        // Check if the set requires an additional file for function
        // definitions.
        if (isset($batch_set['file']) && is_file($batch_set['file'])) {
          include_once $this->root . '/' . $batch_set['file'];
        }
        $queue = $this->getQueue($batch_set);
        $operations = $queue->getAllItems();
        $callable = $this->getCallableResolver()->getCallableFromDefinition($batch_set['finished']);
        $batch_set_result = $callable(...array_merge([
          $batch_set['success'],
          $batch_set['results'],
          $operations,
          $this->dateFormatter->formatInterval((int) ($batch_set['elapsed'] / 1000)),
        ]));
        // If a batch 'finished' callback requested a redirect after the batch
        // is complete, save that for later use. If more than one batch set
        // returned a redirect, the last one is used.
        if ($batch_set_result instanceof RedirectResponse) {
          $batch_finished_redirect = $batch_set_result;
        }
      }
    }

    // Clean up the batch table and unset the static $batch variable.
    if (static::$batch['progressive']) {
      $this->getBatchStorage()->delete(static::$batch['id']);
      foreach (static::$batch['sets'] as $batch_set) {
        if ($queue = $this->getQueue($batch_set)) {
          $queue->deleteQueue();
        }
      }
      // Clean-up the session. Not needed for CLI updates.
      $session = $this->requestStack->getSession();
      $batches = $session->get('batches', []);
      unset($batches[static::$batch['id']]);
      if (empty($batches)) {
        $session->remove('batches');
      }
      else {
        $session->set('batches', $batches);
      }
    }
    $_batch = static::$batch;
    static::$batch = [];

    // Redirect if needed.
    if ($_batch['progressive']) {
      // Revert the 'destination' that was saved in
      // \Drupal\Core\Batch\BatchProcessorInterface::process().
      if (isset($_batch['destination'])) {
        $this->requestStack->getCurrentRequest()->query->set('destination', $_batch['destination']);
      }

      // Determine the target path to redirect to. If a batch 'finished'
      // callback returned a redirect response object, use that. Otherwise,
      // fall back on the form redirection.
      if (isset($batch_finished_redirect)) {
        return $batch_finished_redirect;
      }
      if (!isset($_batch['form_state'])) {
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
            $redirect = $this->getPathValidator()->getUrlIfValid($options['path']);
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
      $redirect = $this->getFormSubmitter()->redirectForm($_batch['form_state']);
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
   * Gets the form submitter.
   *
   * @return \Drupal\Core\Form\FormSubmitterInterface
   *   The form submitter.
   */
  protected function getFormSubmitter(): FormSubmitterInterface {
    return ($this->formSubmitter)();
  }

  /**
   * Gets the path validator.
   *
   * @return \Drupal\Core\Path\PathValidatorInterface
   *   The path validator.
   */
  protected function getPathValidator(): PathValidatorInterface {
    return ($this->pathValidator)();
  }

  /**
   * {@inheritdoc}
   */
  public function shutdown(): void {
    if (static::$batch && $this->needsUpdate()) {
      $this->getBatchStorage()->update(static::$batch);
    }
  }

  /**
   * Retrieves the batch storage instance.
   *
   * @return BatchStorageInterface
   *   The batch storage instance.
   */
  protected function getBatchStorage(): BatchStorageInterface {
    return ($this->batchStorage)();
  }

  /**
   * Gets the callable resolver.
   *
   * @return \Drupal\Core\Utility\CallableResolver
   *   The callable resolver.
   */
  protected function getCallableResolver(): CallableResolver {
    return ($this->callableResolver)();
  }

  /**
   * {@inheritdoc}
   */
  public function needsUpdate(?bool $new_value = NULL): bool {
    if (isset($new_value)) {
      static::$needsUpdate = $new_value;
    }
    return static::$needsUpdate;
  }

}
