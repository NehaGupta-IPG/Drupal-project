<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Url;
use Drupal\node_access_test_auto_bubbling\Controller\NodeAccessTestAutoBubblingController;
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
   * Tests that the node grants cache context is auto-added, only when needed.
   *
   * @see node_query_node_access_alter()
   */
  public function testNodeAccessCacheabilitySafeguard(): void {
    // The node grants cache context should be added when a user without bypass
    // access views the page,
    $this->drupalGet(new Url('node_access_test_auto_bubbling'));
    $this->assertCacheContext('user.node_grants:view');

    // For the same user, test that cache context is added to the render array
    // when the query is run outside the render context from early render.
    $build = NodeAccessTestAutoBubblingController::create(\Drupal::getContainer())->latest();
    // The 'has_render_context' flag should be false to indicate that the query
    // ran outside a render context.
    $this->assertFalse(\Drupal::keyValue('node_access_test')->get('has_render_context'));
    // Now confirm that when rendering the build array, the node grants cache
    // context is present.
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $context = new RenderContext();
    $renderer->executeInRenderContext($context, static fn () => $renderer->render($build));
    /** @var \Drupal\Core\Render\BubbleableMetadata $metadata */
    $metadata = $context->pop();
    $this->assertEquals(['user.node_grants:view'], $metadata->getCacheContexts());

    // The node grants cache context should not be added when the page is viewed
    // as a user with the 'bypass node access' permission.
    $this->drupalLogin($this->drupalCreateUser(['bypass node access']));
    $this->drupalGet(new Url('node_access_test_auto_bubbling'));
    $this->assertNoCacheContext('user.node_grants:view');
    // For the same user with bypass access, confirm that cache context is not
    // added to the render array when the query is run outside the render
    // context from early render.
    $build = NodeAccessTestAutoBubblingController::create(\Drupal::getContainer())->latest();
    $this->assertFalse(\Drupal::keyValue('node_access_test')->get('has_render_context'));
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $context = new RenderContext();
    $renderer->executeInRenderContext($context, static fn () => $renderer->render($build));
    /** @var \Drupal\Core\Render\BubbleableMetadata $metadata */
    $metadata = $context->pop();
    $this->assertEmpty($metadata->getCacheContexts());
    $this->drupalLogout();

    // Uninstall the module with the only hook_node_grants() implementation.
    \Drupal::service('module_installer')->uninstall(['node_access_test']);
    $this->rebuildContainer();

    // Because there are no node grants defined, the node grants cache context
    // should not be added.
    $this->drupalGet(new Url('node_access_test_auto_bubbling'));
    $this->assertNoCacheContext('user.node_grants:view');
    $build = NodeAccessTestAutoBubblingController::create(\Drupal::getContainer())->latest();
    $this->assertFalse(\Drupal::keyValue('node_access_test')->get('has_render_context'));
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $context = new RenderContext();
    $renderer->executeInRenderContext($context, static fn () => $renderer->render($build));
    /** @var \Drupal\Core\Render\BubbleableMetadata $metadata */
    $metadata = $context->pop();
    $this->assertEmpty($metadata->getCacheContexts());
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
