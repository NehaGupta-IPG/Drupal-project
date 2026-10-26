<?php

declare(strict_types=1);

namespace Drupal\Core\Batch;

use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Runs the batch held by \Drupal\Core\Batch\BatchState.
 *
 * Callers outside the Batch API should go through
 * \Drupal\Core\Batch\BatchManagerInterface rather than using this service
 * directly.
 *
 * @internal
 *   This service is an implementation detail of
 *   \Drupal\Core\Batch\BatchManager. It is only public API for the Batch API
 *   itself and may change or be removed at any time.
 */
class BatchProcessor {

  public function __construct(
    #[Autowire(param: 'app.root')]
    protected readonly string $root,
  ) {
    // \Drupal\Core\DrupalKernel::loadLegacyIncludes() loads form.inc, but not
    // batch.inc, and only runs during HTTP request handling. Load both files
    // here so the functions this service wraps are available in every context,
    // such as kernel tests and the command line.
    require_once $root . '/core/includes/form.inc';
    require_once $root . '/core/includes/batch.inc';
  }

  /**
   * Processes the batch.
   *
   * @param \Drupal\Core\Url|string|null $redirect
   *   (optional) Either a path or a Url object to redirect to when the batch
   *   has finished processing.
   * @param \Drupal\Core\Url|null $url
   *   (optional) URL of the batch processing page. Should only be used for
   *   separate scripts like update.php.
   * @param string|null $redirect_callback
   *   (optional) A callable to be called to redirect to the progressive
   *   processing page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive, NULL otherwise.
   *
   * @see \Drupal\Core\Batch\BatchManagerInterface::process()
   */
  public function process(Url|string|null $redirect = NULL, ?Url $url = NULL, ?string $redirect_callback = NULL): ?RedirectResponse {
    return batch_process($redirect, $url, $redirect_callback);
  }

  /**
   * Processes sets in a batch.
   *
   * If the batch was marked for progressive execution (default), this executes
   * as many operations in batch sets until an execution time of 1 second has
   * been exceeded. It will continue with the next operation of the same batch
   * set in the next request.
   *
   * @return array|null|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\JsonResponse
   *   An array containing a completion value (in percent) and a status
   *   message, or a response object.
   */
  public function processQueue(): array|null|RedirectResponse|JsonResponse {
    return _batch_process();
  }

  /**
   * Ends the batch processing.
   *
   * Calls the 'finished' callback of each batch set to allow custom handling
   * of the results and resolves page redirection.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response to the completed page, or NULL to stay at the
   *   current URL.
   *
   * @see \Drupal\Core\Form\FormSubmitter::submitForm()
   */
  public function finish(): ?RedirectResponse {
    return _batch_finished();
  }

  /**
   * Stores the current batch data for the next request.
   *
   * @see \Drupal\Core\DrupalKernel::handle()
   */
  public function shutdown(): void {
    _batch_shutdown();
  }

}
