<?php

declare(strict_types=1);

namespace Drupal\node_access_test_auto_bubbling\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;

/**
 * Returns a node ID listing.
 */
class NodeAccessTestAutoBubblingController extends ControllerBase {

  public function __construct(
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * Lists the three latest published node IDs.
   *
   * @return array
   *   A render array.
   */
  public function latest() {
    // Add markup to the response indicating whether this method and the entity
    // query is run within a render context. The render context is provided by
    // the early rendering subscriber to capture cacheability at build time.
    // Disabling that subscriber makes it possible to test that cacheability
    // from the query properly bubbles to the response at actual render time.
    // @see Drupal\Core\EventSubscriber\EarlyRenderingControllerWrapperSubscriber
    // @see Drupal\node\Hook\NodeDatabaseHooks::queryNodeAccessAlter()
    if ($this->renderer->hasRenderContext()) {
      $build[] = ['#markup' => 'Executed within a render context.'];
    }
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC')
      ->range(0, 3);
    $cacheable_metadata = new BubbleableMetadata();
    $nids = $query->execute($cacheable_metadata);
    $build[] = ['#markup' => $this->t('The three latest nodes are: @nids.', ['@nids' => implode(', ', $nids)])];
    $cacheable_metadata->applyTo($build);
    return $build;
  }

  /**
   * Exposes the node label when a user has access to view a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @return array
   *   A render array.
   */
  public function nodeAccessCacheability(NodeInterface $node) {
    $build = [];
    $cacheability = new CacheableMetadata();
    $access = $node->access('view', return_as_object: TRUE);
    $cacheability->addCacheableDependency($access);
    if ($access->isAllowed()) {
      $build[] = [
        '#markup' => $node->label(),
      ];
      $cacheability->addCacheableDependency($node);
    }
    $cacheability->applyTo($build);
    return $build;
  }

}
