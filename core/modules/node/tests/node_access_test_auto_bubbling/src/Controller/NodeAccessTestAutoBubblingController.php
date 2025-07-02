<?php

declare(strict_types=1);

namespace Drupal\node_access_test_auto_bubbling\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Returns a node ID listing.
 */
class NodeAccessTestAutoBubblingController extends ControllerBase implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    protected readonly KeyValueFactoryInterface $keyValueFactory,
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * Lists the three latest published node IDs.
   *
   * @return array
   *   A render array.
   */
  public function latest() {
    // Set flag in key value storage to assert in tests when the query is
    // executed outside a render context.
    $this->keyValue('node_access_test')->set('has_render_context', $this->renderer->hasRenderContext());
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC')
      ->range(0, 3);
    $nids = $query->execute();
    $build = ['#markup' => $this->t('The three latest nodes are: @nids.', ['@nids' => implode(', ', $nids)])];
    BubbleableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($query)
      ->applyTo($build);
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
