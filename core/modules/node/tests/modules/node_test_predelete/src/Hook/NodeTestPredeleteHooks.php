<?php

declare(strict_types=1);

namespace Drupal\node_test_predelete\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for node_test_predelete.
 */
class NodeTestPredeleteHooks {

  /**
   * Implements hook_ENTITY_TYPE_delete() for node entities.
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    // Only react once per owner so the user delete does not loop forever.
    if (\Drupal::state()->get('node_test_predelete.created')) {
      return;
    }
    \Drupal::state()->set('node_test_predelete.created', TRUE);
    Node::create([
      'type' => $node->bundle(),
      'title' => 'Created during delete',
      'uid' => $node->getOwnerId(),
    ])->save();
  }

}
