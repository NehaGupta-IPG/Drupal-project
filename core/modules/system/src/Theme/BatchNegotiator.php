<?php

namespace Drupal\system\Theme;

use Drupal\Core\Batch\BatchProcessorInterface;
use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sets the active theme for the batch page.
 */
class BatchNegotiator implements ThemeNegotiatorInterface {

  /**
   * The batch storage.
   *
   * @var \Drupal\Core\Batch\BatchStorageInterface
   */
  protected $batchStorage;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a BatchNegotiator.
   *
   * @param \Drupal\Core\Batch\BatchStorageInterface $batch_storage
   *   The batch storage.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack used to retrieve the current request.
   * @param \Drupal\Core\Batch\BatchProcessorInterface|null $batchProcessor
   *    Batch processor.
   *
   * @see https://www.drupal.org/node/3229844
   */
  public function __construct(
    BatchStorageInterface $batch_storage,
    RequestStack $request_stack,
    protected /*readonly*/ ?BatchProcessorInterface $batchProcessor = NULL,
  ) {
    $this->batchStorage = $batch_storage;
    $this->requestStack = $request_stack;
    if ($this->batchProcessor === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $batchProcessor argument is deprecated in drupal:11.3.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3229844', E_USER_DEPRECATED);
      $this->batchProcessor = \Drupal::service(BatchProcessorInterface::class);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'system.batch_page.html';
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    // Retrieve the current state of the batch.
    $request = $this->requestStack->getCurrentRequest();
    $batch = &$this->batchProcessor->getCurrentBatch();
    if (!$batch && $request->request->has('id')) {
      $batch = $this->batchStorage->load($request->request->get('id'));
    }
    // Use the same theme as the page that started the batch.
    if (!empty($batch['theme'])) {
      return $batch['theme'];
    }
  }

}
