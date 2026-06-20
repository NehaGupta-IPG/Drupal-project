<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Plugin\TreegridColumn\EnabledColumn;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests EnabledColumn cell data shape and persistence in isolation.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class EnabledColumnTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'system',
    'user',
  ];

  /**
   * The column plugin under test.
   *
   * @var \Drupal\menu_tree_ui\Plugin\TreegridColumn\EnabledColumn
   */
  private EnabledColumn $column;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    /** @var \Drupal\treegrid\TreegridColumnManager $manager */
    $manager = $this->container->get('plugin.manager.treegrid_column');
    $this->column = $manager->createInstance('enabled');
  }

  /**
   * The column sorts ahead of "Expanded".
   */
  public function testSortsBeforeExpanded(): void {
    $columns = array_keys($this->container->get('plugin.manager.treegrid_column')->getSortedColumns());
    $this->assertLessThan(
      array_search('expanded', $columns, TRUE),
      array_search('enabled', $columns, TRUE),
      'The enabled column should sort before the expanded column.',
    );
  }

  /**
   * GetCellData() returns a toggle for every link, including leaves.
   */
  public function testGetCellDataAppliesToLeaves(): void {
    $entity = $this->createMenuLinkContent(enabled: TRUE);
    $element = $this->elementFromEntity($entity, hasChildren: FALSE);

    $cell = $this->column->getCellData($element);

    $this->assertSame('toggle', $cell['type']);
    $this->assertTrue($cell['value']);
    $this->assertIsString($cell['label']);
    $this->assertNotEmpty($cell['label']);
    $this->assertTrue($cell['interactive']);
    $this->assertStringStartsWith('menu_link_content:', $cell['link_id']);
  }

  /**
   * GetCellData() must reflect value=false for a disabled link.
   */
  public function testGetCellDataReflectsCurrentValue(): void {
    $entity = $this->createMenuLinkContent(enabled: FALSE);
    $element = $this->elementFromEntity($entity, hasChildren: FALSE);

    $cell = $this->column->getCellData($element);

    $this->assertFalse($cell['value']);
  }

  /**
   * HandleUpdate() to FALSE must persist and return TRUE.
   */
  public function testHandleUpdatePersistsFalse(): void {
    $entity = $this->createMenuLinkContent(enabled: TRUE);

    $result = $this->column->handleUpdate($entity, FALSE);

    $this->assertTrue($result);
    $this->assertPersistedEnabledValue($entity->id(), FALSE);
  }

  /**
   * HandleUpdate() to TRUE must persist and return TRUE.
   */
  public function testHandleUpdatePersistsTrue(): void {
    $entity = $this->createMenuLinkContent(enabled: FALSE);

    $result = $this->column->handleUpdate($entity, TRUE);

    $this->assertTrue($result);
    $this->assertPersistedEnabledValue($entity->id(), TRUE);
  }

  /**
   * HandleUpdate() must return FALSE for an unsupported entity type.
   */
  public function testHandleUpdateReturnsFalseForNonMenuLinkContent(): void {
    $user = User::create(['name' => 'test_user_' . mt_rand()]);
    $user->save();

    $result = $this->column->handleUpdate($user, TRUE);

    $this->assertFalse($result, 'handleUpdate() must return FALSE for entity types it does not support.');
  }

  // Helpers.

  /**
   * Creates and saves a menu_link_content entity for testing.
   */
  private function createMenuLinkContent(bool $enabled): MenuLinkContentInterface {
    $entity = MenuLinkContent::create([
      'title' => 'Test link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'main',
      'enabled' => $enabled,
    ]);
    $entity->save();
    return $entity;
  }

  /**
   * Wraps a saved entity in a MenuLinkTreeElement via the plugin manager.
   */
  private function elementFromEntity(MenuLinkContentInterface $entity, bool $hasChildren): MenuLinkTreeElement {
    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $link_manager */
    $link_manager = $this->container->get('plugin.manager.menu.link');
    $link_manager->rebuild();

    $plugin_id = 'menu_link_content:' . $entity->uuid();
    $link = $link_manager->createInstance($plugin_id);

    return new MenuLinkTreeElement($link, $hasChildren, 1, FALSE, []);
  }

  /**
   * Reloads the entity and asserts its persisted enabled value.
   */
  private function assertPersistedEnabledValue(int|string $entity_id, bool $expected): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $storage->resetCache([$entity_id]);
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $reloaded */
    $reloaded = $storage->load($entity_id);
    $this->assertSame($expected, (bool) $reloaded->get('enabled')->value);
  }

}
