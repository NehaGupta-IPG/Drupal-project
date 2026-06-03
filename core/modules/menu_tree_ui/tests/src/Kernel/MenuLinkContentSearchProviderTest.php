<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\SearchProvider\MenuLinkContentSearchProvider;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MenuLinkContentSearchProvider, including the ancestor chain shape.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuLinkContentSearchProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'system',
    'user',
  ];

  /**
   * The search provider under test.
   *
   * @var \Drupal\menu_tree_ui\SearchProvider\MenuLinkContentSearchProvider
   */
  private MenuLinkContentSearchProvider $provider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();

    $this->provider = $this->container->get('menu_tree_ui.search_provider.menu_link_content');
  }

  /**
   * Ancestor breadcrumb for a 3-level tree must be [root, mid] (excludes self).
   *
   * Tree: A (root) -> A1 (mid) -> A1a (leaf, searched).
   *
   * This pins the getRootPathIds() contract: the method includes the link itself
   * at index 0, and we drop it. The breadcrumb must contain exactly the two
   * ancestors in root-first order.
   */
  public function testAncestorChainShapeForThreeLevelTree(): void {
    // Build A -> A1 -> A1a. Each entity must be saved before its child so
    // the plugin manager can resolve the parent plugin ID.
    $link_manager = $this->container->get('plugin.manager.menu.link');

    $a = MenuLinkContent::create([
      'title' => 'A',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'test',
    ]);
    $a->save();
    $link_manager->rebuild();

    $a1 = MenuLinkContent::create([
      'title' => 'A1',
      'link' => ['uri' => 'internal:/a'],
      'menu_name' => 'test',
      'parent' => $a->getPluginId(),
    ]);
    $a1->save();
    $link_manager->rebuild();

    $a1a = MenuLinkContent::create([
      'title' => 'A1a',
      'link' => ['uri' => 'internal:/a/a1'],
      'menu_name' => 'test',
      'parent' => $a1->getPluginId(),
    ]);
    $a1a->save();
    $link_manager->rebuild();

    $results = $this->provider->search('test', 'A1a', 25);

    $this->assertCount(1, $results, 'Exactly one result must be found.');
    $result = $results[0];
    $this->assertSame('A1a', $result['title']);

    // Breadcrumb must contain the two ancestors in root-first order.
    $this->assertCount(2, $result['breadcrumb'], 'Breadcrumb must list exactly 2 ancestors (A and A1).');
    $this->assertSame('A', $result['breadcrumb'][0], 'Root ancestor must be first.');
    $this->assertSame('A1', $result['breadcrumb'][1], 'Direct parent must be second.');

    $this->assertCount(2, $result['breadcrumb_ids'], 'breadcrumb_ids must match breadcrumb length.');
    $this->assertSame($a->getPluginId(), $result['breadcrumb_ids'][0]);
    $this->assertSame($a1->getPluginId(), $result['breadcrumb_ids'][1]);
  }

  /**
   * A leaf at the top level must have an empty breadcrumb.
   */
  public function testTopLevelLinkHasEmptyBreadcrumb(): void {
    $link_manager = $this->container->get('plugin.manager.menu.link');

    $root = MenuLinkContent::create([
      'title' => 'RootOnly',
      'link' => ['uri' => 'internal:/root'],
      'menu_name' => 'test',
    ]);
    $root->save();
    $link_manager->rebuild();

    $results = $this->provider->search('test', 'RootOnly', 25);

    $this->assertCount(1, $results);
    $this->assertSame([], $results[0]['breadcrumb']);
    $this->assertSame([], $results[0]['breadcrumb_ids']);
  }

}
