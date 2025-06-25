<?php

declare(strict_types=1);

namespace Drupal\node;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;

/**
 * Defines the NodeRepository service.
 */
class NodeRepository implements NodeRepositoryInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CurrentRouteMatch $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isPage(NodeInterface $node): bool {
    $route_name = $this->routeMatch->getRouteName();
    $page_node = $route_name === 'entity.node.canonical'
    ? $this->routeMatch->getParameter('node')
    : NULL;

    $is_page = $page_node instanceof NodeInterface && $page_node->id() === $node->id();

    return $is_page;
  }

}
