<?php

declare(strict_types=1);

namespace Drupal\paramconverter_test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;

/**
 * Controller routine for testing the paramconverter.
 */
class TestControllers {

  /**
   * Returns a render array with markup for the user, node and foo parameters.
   */
  public function testUserNodeFoo(EntityInterface $user, NodeInterface $node, $foo) {
    $foo = is_object($foo) ? $foo->label() : $foo;
    return ['#markup' => "user: {$user->label()}, node: {$node->label()}, foo: $foo"];
  }

  /**
   * Returns a render array with markup containing parent node information.
   */
  public function testNodeSetParent(NodeInterface $node, NodeInterface $parent) {
    return ['#markup' => "Setting '{$parent->label()}' as parent of '{$node->label()}'."];
  }

  /**
   * Returns a cacheable render array containing the node label.
   */
  public function testEntityLanguage(NodeInterface $node) {
    $build = ['#markup' => $node->label()];
    \Drupal::service('renderer')->addCacheableDependency($build, $node);
    return $build;
  }

}
