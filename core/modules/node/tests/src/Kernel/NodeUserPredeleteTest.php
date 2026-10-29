<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests deleting a user whose nodes are saved while the user is deleted.
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class NodeUserPredeleteTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'system',
    'field',
    'text',
    'filter',
    'node_test_predelete',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['filter', 'node']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * A node owned by the user that appears mid-delete is deleted too.
   */
  public function testNodeSavedDuringUserDelete(): void {
    $user = $this->createUser();
    $node = Node::create(['type' => 'page', 'title' => 'Owned', 'uid' => $user->id()]);
    $node->save();

    $user->delete();

    $nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->execute();
    $this->assertSame([], $nids, 'No node of the deleted user remains.');
  }

}
