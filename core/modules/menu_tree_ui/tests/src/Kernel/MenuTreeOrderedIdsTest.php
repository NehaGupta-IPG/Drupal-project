<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that orderedChildIds() gives core's display order on a monolingual site.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeOrderedIdsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'treegrid_reorder',
    'system',
    'user',
  ];

  /**
   * The loader under test.
   */
  private MenuTreeLoader $loader;

  /**
   * Plugin IDs of the top-level content links, keyed by title.
   *
   * @var array<string, string>
   */
  private array $topLevelLinks = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
    $this->container->get('plugin.manager.menu.link')->rebuild();
    $this->setUpCurrentUser(['uid' => 1], ['administer menu']);
    $this->loader = $this->container->get('menu_tree_ui.loader');

    $fixture = [
      ['Foo', 0, ''],
      ['Foo Bar', 0, ''],
      ['Alpha', 0, ''],
      ['Beta', 0, ''],
      ['Zed', -5, ''],
      ['Negative', -1, ''],
      ['Heavy', 10, ''],
      ['Tie one', 3, ''],
      ['Tie two', 3, ''],
      ['Tie one a', 3, ''],
    ];
    foreach ($fixture as [$title, $weight, $parent]) {
      $this->createLink($title, $weight, $parent);
      $this->createLink($title, $weight, 'system.admin');
    }
    $this->createLink('Disabled', 0, '', FALSE);
  }

  /**
   * The top level matches core's sort.
   */
  public function testTopLevelMatchesCore(): void {
    $ids = $this->loader->orderedChildIds('admin', '');
    $this->assertSame($this->coreOrder(''), $ids);
    $this->assertContains($this->topLevelLinks['Disabled'], $ids);
  }

  /**
   * A child level mixing static and content links matches core's sort.
   */
  public function testChildLevelMatchesCore(): void {
    $ids = $this->loader->orderedChildIds('admin', 'system.admin');
    $this->assertSame($this->coreOrder('system.admin'), $ids);
    $static = array_filter($ids, static fn(string $id): bool => !str_starts_with($id, 'menu_link_content:'));
    $this->assertNotEmpty($static);
    $this->assertNotEmpty(array_diff($ids, $static));
  }

  /**
   * A title that is a prefix of another at the same weight sorts as in core.
   */
  public function testPrefixTitles(): void {
    $ids = $this->loader->orderedChildIds('admin', '');
    $this->assertLessThan(
      array_search($this->topLevelLinks['Foo'], $ids, TRUE),
      array_search($this->topLevelLinks['Foo Bar'], $ids, TRUE),
    );
  }

  /**
   * Returns a level's ids as core's generateIndexAndSort() orders them.
   *
   * @return list<string>
   *   The plugin IDs.
   */
  private function coreOrder(string $parent_id): array {
    $params = new MenuTreeParameters();
    if ($parent_id !== '') {
      $params->setRoot($parent_id)->excludeRoot();
    }
    $params->setMaxDepth(1);
    $menu_tree = $this->container->get('menu.link_tree');
    $tree = $menu_tree->load('admin', $params);
    $tree = $menu_tree->transform($tree, [['callable' => 'menu.default_tree_manipulators:generateIndexAndSort']]);
    return array_values(array_map(
      static fn(MenuLinkTreeElement $element): string => $element->link->getPluginId(),
      $tree,
    ));
  }

  /**
   * Creates a content link in the admin menu and returns its plugin ID.
   *
   * A top-level link is also remembered by its title.
   */
  private function createLink(string $title, int $weight, string $parent, bool $enabled = TRUE): string {
    $entity = MenuLinkContent::create([
      'title' => $title,
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'admin',
      'weight' => $weight,
      'parent' => $parent,
      'enabled' => $enabled,
    ]);
    $entity->save();
    if ($parent === '') {
      $this->topLevelLinks[$title] = $entity->getPluginId();
    }
    return $entity->getPluginId();
  }

}
