<?php

namespace Drupal\system\Controller;

use Drupal\Core\Batch\BatchProcessorInterface;
use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for batch routes.
 */
class BatchController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new BatchController.
   *
   * @param string $root
   *   The app root.
   * @param \Drupal\Core\Batch\BatchStorageInterface $batchStorage
   *   Batch storage.
   * @param \Drupal\Core\Batch\BatchProcessorInterface|null $batchProcessor
   *   Batch processor.
   *
   * @see https://www.drupal.org/node/3229844
   */
  public function __construct(
    protected string $root,
    protected BatchStorageInterface $batchStorage,
    protected /*readonly*/ ?BatchProcessorInterface $batchProcessor = NULL,
  ) {
    require_once $this->root . '/core/includes/batch.inc';
    if ($this->batchProcessor === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $batchProcessor argument is deprecated in drupal:11.4.0 and it will be required in drupal:13.0.0. See https://www.drupal.org/node/3229844', E_USER_DEPRECATED);
      $this->batchProcessor = \Drupal::service(BatchProcessorInterface::class);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->getParameter('app.root'),
      $container->get('batch.storage'),
      $container->get(BatchProcessorInterface::class),
    );
  }

  /**
   * Returns a system batch page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response|array
   *   A \Symfony\Component\HttpFoundation\Response object or render array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function batchPage(Request $request) {
    $output = _batch_page($request);

    if ($output === FALSE) {
      throw new AccessDeniedHttpException();
    }
    elseif ($output instanceof Response) {
      return $output;
    }
    elseif (isset($output)) {
      $title = $output['#title'] ?? NULL;
      $page = [
        '#type' => 'page',
        '#title' => $title,
        '#show_messages' => FALSE,
        'content' => $output,
      ];

      // Also inject title as a page header (if available).
      if ($title) {
        $page['header'] = [
          '#type' => 'page_title',
          '#title' => $title,
        ];
      }

      return $page;
    }
  }

  /**
   * The _title_callback for the system.batch_page.html route.
   *
   * @return string
   *   The page title.
   */
  public function batchPageTitle(Request $request) {
    $batch = &$this->batchProcessor->getCurrentBatch();

    if (!($request_id = $request->query->get('id'))) {
      return '';
    }

    // Retrieve the current state of the batch.
    if (!$batch) {
      $batch = $this->batchStorage->load($request_id);
    }

    if (!$batch) {
      return '';
    }

    $current_set = $this->batchProcessor->getCurrentSet();
    return !empty($current_set['title']) ? $current_set['title'] : '';
  }

}
