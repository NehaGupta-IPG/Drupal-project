<?php

declare(strict_types=1);

namespace Drupal\node;

/**
 * Defines an interface for node repository services.
 */
interface NodeRepositoryInterface {

  /**
   * Determines if the given node is the one being displayed as a full page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return bool
   *   TRUE if this is the canonical page view of the node, FALSE otherwise.
   */
  public function isPage(NodeInterface $node): bool;

}
