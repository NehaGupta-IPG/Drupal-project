<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the output of node links (read more, add new comment, etc).
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class NodeLinksTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views', 'node_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the links can be hidden in the view display settings.
   */
  public function testHideLinks(): void {
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'promote' => NodeInterface::PROMOTED,
    ]);

    // Links are displayed by default.
    $this->drupalGet('node');
    $this->assertSession()->pageTextContains($node->getTitle());
    $this->assertSession()->linkExists('Read more');

    // Hide links.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article', 'teaser')
      ->removeComponent('links')
      ->save();

    $this->drupalGet('node');
    $this->assertSession()->pageTextContains($node->getTitle());
    $this->assertSession()->linkNotExists('Read more');
  }

  /**
   * Tests altering the entity type changes the route path.
   */
  public function testNodeRouteAlter(): void {
    // Login with user with right permissions and test listing.
    $this->drupalLogin($this->drupalCreateUser([
      'administer nodes',
      'access content overview',
      'edit any page content',
      'administer nodes',
      'bypass node access',
    ]));
    // Create a node with no usage.
    $node = $this->createNode();
    $this->drupalGet('admin/content');

    // Verify default route path.
    $this->assertSession()->linkByHrefExists('/node/' . $node->id());
    $this->assertSession()->linkByHrefExists('/node/' . $node->id() . '/edit');
    $this->assertSession()->linkByHrefExists('/node/' . $node->id() . '/delete');

    // Trigger hook entity_type_alter node route change.
    $this->container->get('state')->set('node_test_route_alter', TRUE);

    // Clear the entity type manager cache so that
    // the altered definition is picked up.
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('router.builder')->rebuild();
    $this->container->get('cache.render')->deleteAll();

    $this->drupalGet('admin/content');

    // Verify new route path.
    $this->assertSession()->linkByHrefExists('/route_alter/' . $node->id());
    $this->assertSession()->linkByHrefExists('/route_alter/' . $node->id() . '/edit');
    $this->assertSession()->linkByHrefExists('/route_alter/' . $node->id() . '/delete');

  }

}
