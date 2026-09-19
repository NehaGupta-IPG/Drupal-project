<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\node\NodeAccessRebuild;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the node access automatic cacheability bubbling logic.
 */
#[Group('node')]
#[Group('Cache')]
#[Group('cacheability_safeguards')]
#[RunTestsInSeparateProcesses]
class NodeAccessCacheabilityTest extends NodeTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node_access_test',
    'node_access_test_auto_bubbling',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service(NodeAccessRebuild::class)->rebuild();

    // Create some content.
    $this->drupalCreateNode();
    $this->drupalCreateNode();
    $this->drupalCreateNode();
    $this->drupalCreateNode();
  }

  /**
   * Tests that the user cache contexts are correctly set.
   */
  public function testNodeAccessCacheContext(): void {
    // Create a user, with edit/delete own content permission.
    $test_user1 = $this->drupalCreateUser([
      'access content',
      'edit own page content',
      'delete own page content',
    ]);

    $this->drupalLogin($test_user1);

    $node1 = $this->createNode(['type' => 'page']);

    // User should be able to edit/delete their own content.
    // Therefore after the access check in node_node_access the user cache
    // context should be added.
    $this->drupalGet('node/' . $node1->id() . '/edit');
    $this->assertCacheContext('user');
    $this->drupalGet('node/' . $node1->id() . '/delete');
    $this->assertCacheContext('user');

    // Create a user without edit/delete permission.
    $test_user2 = $this->drupalCreateUser([
      'access content',
    ]);

    $this->drupalLogin($test_user2);

    $node2 = $this->createNode(['type' => 'page']);

    // The user shouldn't have access to the node edit/delete pages.
    // Therefore after the access check in node_node_access the user permissions
    // cache context should be added.
    $this->drupalGet('node/' . $node2->id() . '/edit');
    $this->assertCacheContext('user.permissions');
    $this->drupalGet('node/' . $node2->id() . '/delete');
    $this->assertCacheContext('user.permissions');
  }

}
