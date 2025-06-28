<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the node access automatic cacheability bubbling logic.
 */
#[Group('node')]
#[Group('Cache')]
#[Group('cacheability_safeguards')]
class NodeAccessCacheabilityTest extends EntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'node_access_test',
    'node_access_test_auto_bubbling',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);

    node_access_rebuild();

    // Create some content.
    $this->createNode();
  }

  /**
   * Tests that the node grants cache context is auto-added, only when needed.
   *
   * @see node_query_node_access_alter()
   */
  public function testNodeAccessCacheabilitySafeguard(): void {
    // The node grants cache context should be added automatically on a node
    // entity query for a user without 'bypass node access' permission.
    $user = $this->createUser();
    \Drupal::currentUser()->setAccount($user);
    $query = $this->getEntityQuery();
    $query->execute();
    $this->assertEquals(['user.node_grants:view'], $query->getCacheContexts());

    // The user has the 'bypass node access' permission, which means the
    // node grants cache context is not added to a node entity query.
    \Drupal::currentUser()->setAccount($this->createUser(['bypass node access']));
    $query = $this->getEntityQuery();
    $query->execute();
    $this->assertEmpty($query->getCacheContexts());

    // Uninstall the module with the only hook_node_grants() implementation.
    \Drupal::service('module_installer')->uninstall(['node_access_test']);

    // Because there are no node grants defined, the node grants cache context
    // is not added to the query.
    \Drupal::currentUser()->setAccount($user);
    $query = $this->getEntityQuery();
    $query->execute();
    $this->assertEmpty($query->getCacheContexts());
  }

  /**
   * Gets an entity query object.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function getEntityQuery(): QueryInterface {
    $query = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC')
      ->range(0, 1);
    $this->assertInstanceOf(RefinableCacheableDependencyInterface::class, $query);
    return $query;
  }

}
