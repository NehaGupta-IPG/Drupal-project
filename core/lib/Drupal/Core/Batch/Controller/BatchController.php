<?php

namespace Drupal\Core\Batch\Controller;

use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\Attribute\Route;
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
   */
  public function __construct(
    protected string $root,
    protected BatchStorageInterface $batchStorage,
  ) {
    require_once $this->root . '/core/includes/batch.inc';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->getParameter('app.root'),
      $container->get('batch.storage'),
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
  #[Route(
    path: '/batch',
    name: 'system.batch_page.html',
    requirements: ['_access' => 'TRUE', '_format' => 'html'],
    options: ['_admin_route' => TRUE],
    defaults: ['_title_callback' => '\Drupal\Core\Batch\Controller\BatchController::batchPageTitle'],
  )]
  #[Route(
    path: '/batch',
    name: 'system.batch_page.json',
    requirements: ['_access' => 'TRUE', '_format' => 'json'],
    options: ['_admin_route' => TRUE],
  )]
  public function batchPage(Request $request): Response|array {
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
    $batch = &batch_get();

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

    $current_set = _batch_current_set();
    return !empty($current_set['title']) ? $current_set['title'] : '';
  }

}
