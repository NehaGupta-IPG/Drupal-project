<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Menu;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests integration of static menu links.
 */
#[Group('Menu')]
#[RunTestsInSeparateProcesses]
class MenuLinkDefaultIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_test',
    'menu_link_content',
    'link',
    'system',
    'user',
  ];

  /**
   * The menu link plugin manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');

    $this->menuLinkManager = \Drupal::service('plugin.manager.menu.link');
  }

  /**
   * Tests moving a static menu link without a specified menu to the root.
   */
  public function testMoveToRoot(): void {
    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager */
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    $menu_link_manager->rebuild();

    $menu_link = $menu_link_manager->getDefinition('menu_test.child');
    $this->assertEquals('menu_test.parent', $menu_link['parent']);
    $this->assertEquals('test', $menu_link['menu_name']);

    $tree = \Drupal::menuTree()->load('test', new MenuTreeParameters());
    $this->assertCount(1, $tree);
    $this->assertEquals('menu_test.parent', $tree['menu_test.parent']->link->getPluginId());
    $this->assertEquals('menu_test.child', $tree['menu_test.parent']->subtree['menu_test.child']->link->getPluginId());

    // Ensure that the menu name is not forgotten.
    $menu_link_manager->updateDefinition('menu_test.child', ['parent' => '']);
    $menu_link = $menu_link_manager->getDefinition('menu_test.child');

    $this->assertEquals('', $menu_link['parent']);
    $this->assertEquals('test', $menu_link['menu_name']);

    $tree = \Drupal::menuTree()->load('test', new MenuTreeParameters());
    $this->assertCount(2, $tree);
    $this->assertEquals('menu_test.parent', $tree['menu_test.parent']->link->getPluginId());
    $this->assertEquals('menu_test.child', $tree['menu_test.child']->link->getPluginId());

    $this->assertTrue(TRUE);
  }

  /**
   * Tests updateDefinition order fix for https://www.drupal.org/node/3181070.
   */
  public function testUpdateDefinitionTreeOrder(): void {
    Menu::create([
      'id' => 'menu-test',
      'label' => 'Test menu',
    ])->save();

    $parent = MenuLinkContent::create([
      'title' => 'Parent link',
      'link' => ['uri' => 'internal:/parent'],
      'menu_name' => 'menu-test',
    ]);
    $parent->save();
    $parent_plugin_id = $parent->getPluginId();

    $child = MenuLinkContent::create([
      'title' => 'Child link',
      'link' => ['uri' => 'internal:/child'],
      'menu_name' => 'menu-test',
      'parent' => $parent_plugin_id,
    ]);
    $child->save();
    $child_plugin_id = $child->getPluginId();

    $tree = \Drupal::menuTree()->load('menu-test', new MenuTreeParameters());
    $this->assertCount(1, $tree);
    $this->assertArrayHasKey($parent_plugin_id, $tree);
    $this->assertArrayHasKey($child_plugin_id, $tree[$parent_plugin_id]->subtree);

    $this->menuLinkManager->updateDefinition($child_plugin_id, ['parent' => '']);

    $hook_state = \Drupal::state()->get('menu_test.presave_parent');
    $this->assertNotNull($hook_state);
    $this->assertEquals('', $hook_state['expected']);
    $this->assertEquals('', $hook_state['actual']);

    $tree = \Drupal::menuTree()->load('menu-test', new MenuTreeParameters());
    $this->assertCount(2, $tree);
    $this->assertArrayHasKey($parent_plugin_id, $tree);
    $this->assertArrayHasKey($child_plugin_id, $tree);

    $definition = $this->menuLinkManager->getDefinition($child_plugin_id);
    $this->assertEquals('', $definition['parent']);

    $entity = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $child->uuid());
    $this->assertEquals('', $entity->getParentId());
  }

}
