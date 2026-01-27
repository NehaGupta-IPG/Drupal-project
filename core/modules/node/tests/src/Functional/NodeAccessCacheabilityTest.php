<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\Core\Url;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\node\NodeAccessRebuild;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

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
   * Tests that the node grants cache context is auto-added, only when needed.
   *
   * Test with early rendering both enabled and disabled, because early
   * rendering provides a render context that will capture cache metadata from
   * a node_access query. Without the render context from early rendering, the
   * cache metadata is retrieved from the select object and merged to the
   * controller's build array cache metadata to bubble up.
   *
   * @see \Drupal\node\Hook\NodeDatabaseHooks::queryNodeAccessAlter()
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testNodeAccessCacheabilitySafeguard(bool $disable_early_render): void {
    \Drupal::keyValue('node_access_test')->set('disable_early_rendering', $disable_early_render);
    $this->assertNull(\Drupal::keyValue('node_access_test')->get('has_render_context'));

    $this->drupalGet(new Url('node_access_test_auto_bubbling'));
    $this->assertCacheContext('user.node_grants:view');
    // The controller sets the 'has_render_context' value based on whether it
    // runs within a render context. The render context is provided by the early
    // rendering subscriber when it is enabled, and there is no render context
    // when early rendering is disabled.
    $this->assertSame(\Drupal::keyValue('node_access_test')->get('has_render_context'), !$disable_early_render);
    \Drupal::keyValue('node_access_test')->delete('has_render_context');

    // The user has the 'bypass node access' permission, which means the
    // node grants cache context is not necessary.
    $this->drupalLogin($this->drupalCreateUser(['bypass node access']));
    $this->drupalGet(new Url('node_access_test_auto_bubbling'));
    $this->assertNoCacheContext('user.node_grants:view');
    $this->drupalLogout();
    $this->assertSame(\Drupal::keyValue('node_access_test')->get('has_render_context'), !$disable_early_render);
    \Drupal::keyValue('node_access_test')->delete('has_render_context');

    // Uninstall the module with the only hook_node_grants() implementation.
    $this->container->get('module_installer')->uninstall(['node_access_test']);
    $this->rebuildContainer();

    // Because there are no node grants defined, there also is no need for the
    // node grants cache context to be bubbled.
    $this->drupalGet(new Url('node_access_test_auto_bubbling'));
    $this->assertNoCacheContext('user.node_grants:view');
    $this->assertSame(\Drupal::keyValue('node_access_test')->get('has_render_context'), !$disable_early_render);
    \Drupal::keyValue('node_access_test')->delete('has_render_context');
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
