<?php

namespace Drupal\Core\Batch;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Implements the default batch processor.
 *
 * @internal
 */
class BatchProcessor implements BatchProcessorInterface {

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
   * @param \Closure $connection
   *   The database connection.
   * @param \Closure $batchStorage
   *   The batch storage.
   * @param \Closure $pathValidator
   *   The path validator.
   * @param \Closure $formSubmitter
   *   The form submitter.
   */
  public function __construct(
    protected readonly string $root,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly RequestStack $requestStack,
    protected readonly \Closure $connection,
    protected readonly \Closure $batchStorage,
    protected readonly \Closure $pathValidator,
    protected readonly \Closure $formSubmitter,
  ) {
    require_once $root . '/core/includes/batch.inc';
    require_once $root . '/core/includes/form.inc';
  }

  /**
   * {@inheritdoc}
   */
  public function queue(array $batch_definition): void {
    batch_set($batch_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function appendSet(array &$batch, array $batch_set): void {
    _batch_append_set($batch, $batch_set);
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function queuePopulate(array &$batch, string $set_id): void {
    _batch_populate_queue($batch, $set_id);
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
    return batch_process($redirect, $url, $redirect_callback);
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
    return _batch_process();
  }

  /**
   * {@inheritdoc}
   */
  public function &getCurrentSet(): array {
    return _batch_current_set();
  }

  /**
   * {@inheritdoc}
   */
  public function nextSet(): bool {
    $batch = &$this->getCurrentBatch();
    $set_indexes = array_keys($batch['sets']);
    $current_set_index_key = array_search($batch['current_set'], $set_indexes);
    if (isset($set_indexes[$current_set_index_key + 1])) {
      $batch['current_set'] = $set_indexes[$current_set_index_key + 1];
      $current_set = &$this->getCurrentSet();
      if (isset($current_set['form_submit']) && ($callback = $current_set['form_submit']) && is_callable($callback)) {
        // We use our stored copies of $form and $form_state to account for
        // possible alterations by previous form submit handlers.
        $complete_form = &$batch['form_state']->getCompleteForm();
        call_user_func_array($callback, [&$complete_form, &$batch['form_state']]);
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
    $batch = &$this->getCurrentBatch();
    $batch_finished_redirect = NULL;

    // Execute the 'finished' callbacks for each batch set, if defined.
    foreach ($batch['sets'] as $batch_set) {
      if (isset($batch_set['finished'])) {
        // Check if the set requires an additional file for function definitions.
        if (isset($batch_set['file']) && is_file($batch_set['file'])) {
          include_once $this->root . '/' . $batch_set['file'];
        }
        if (is_callable($batch_set['finished'])) {
          $queue = $this->getQueue($batch_set);
          $operations = $queue->getAllItems();
          $batch_set_result = call_user_func_array($batch_set['finished'], [$batch_set['success'], $batch_set['results'], $operations, $this->dateFormatter->formatInterval((int) ($batch_set['elapsed'] / 1000))]);
          // If a batch 'finished' callback requested a redirect after the batch
          // is complete, save that for later use. If more than one batch set
          // returned a redirect, the last one is used.
          if ($batch_set_result instanceof RedirectResponse) {
            $batch_finished_redirect = $batch_set_result;
          }
        }
      }
    }

    // Clean up the batch table and unset the static $batch variable.
    if ($batch['progressive']) {
      $this->getBatchStorage()->delete($batch['id']);
      foreach ($batch['sets'] as $batch_set) {
        if ($queue = $this->getQueue($batch_set)) {
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

      // Determine the target path to redirect to. If a batch 'finished' callback
      // returned a redirect response object, use that. Otherwise, fall back on
      // the form redirection.
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
      $redirect = $this->getFormSubmiter()->redirectForm($_batch['form_state']);
      if (is_object($redirect)) {
        return $redirect;
      }

      // If no redirection happened, redirect to the originating page. In case the
      // form needs to be rebuilt, save the final $form_state for
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
  protected function getFormSubmiter(): FormSubmitterInterface {
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
    if (($batch = $this->getCurrentBatch()) && $this->needsUpdate()) {
      $this->getBatchStorage()->update($batch);
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
   * {@inheritdoc}
   */
  public function needsUpdate(?bool $new_value = NULL): bool {
    if (isset($new_value)) {
      static::$needsUpdate = $new_value;
    }
    return static::$needsUpdate;
  }


}
