<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\user\Entity\User;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Plugin\MenuTreeColumn\ExpandedColumn;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ExpandedColumn cell data shape and persistence in isolation.
 *
 * @group menu_tree_ui
 */
#[RunTestsInSeparateProcesses]
final class ExpandedColumnTest extends KernelTestBase {

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
   * The column plugin under test.
   *
   * @var \Drupal\menu_tree_ui\Plugin\MenuTreeColumn\ExpandedColumn
   */
  private ExpandedColumn $column;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    /** @var \Drupal\menu_tree_ui\MenuTreeColumnManager $manager */
    $manager = $this->container->get('plugin.manager.menu_tree_column');
    $this->column = $manager->createInstance('expanded');
  }

  /**
   * GetCellData() must return [] for leaf items (hasChildren = false).
   */
  public function testGetCellDataReturnsEmptyForLeaf(): void {
    $entity = $this->createMenuLinkContent(expanded: FALSE);
    $element = $this->elementFromEntity($entity, hasChildren: FALSE);

    $cell = $this->column->getCellData($element);

    $this->assertSame([], $cell);
  }

  /**
   * GetCellData() must return the expected shape for a non-expanded parent.
   */
  public function testGetCellDataShapeNotExpanded(): void {
    $entity = $this->createMenuLinkContent(expanded: FALSE);
    $element = $this->elementFromEntity($entity, hasChildren: TRUE);

    $cell = $this->column->getCellData($element);

    $this->assertSame('toggle', $cell['type']);
    $this->assertFalse($cell['value']);
    $this->assertIsString($cell['label']);
    $this->assertNotEmpty($cell['label']);
    $this->assertTrue($cell['interactive']);
    $this->assertStringStartsWith('menu_link_content:', $cell['link_id']);
  }

  /**
   * GetCellData() must reflect value=true when the parent link is expanded.
   */
  public function testGetCellDataReflectsCurrentValue(): void {
    $entity = $this->createMenuLinkContent(expanded: TRUE);
    $element = $this->elementFromEntity($entity, hasChildren: TRUE);

    $cell = $this->column->getCellData($element);

    $this->assertTrue($cell['value']);
  }

  /**
   * HandleUpdate() to TRUE must persist and return TRUE.
   */
  public function testHandleUpdatePersistsTrue(): void {
    $entity = $this->createMenuLinkContent(expanded: FALSE);

    $result = $this->column->handleUpdate($entity, TRUE);

    $this->assertTrue($result);
    $this->assertPersistedExpandedValue($entity->id(), TRUE);
  }

  /**
   * HandleUpdate() to FALSE must persist and return TRUE.
   */
  public function testHandleUpdatePersistsFalse(): void {
    $entity = $this->createMenuLinkContent(expanded: TRUE);

    $result = $this->column->handleUpdate($entity, FALSE);

    $this->assertTrue($result);
    $this->assertPersistedExpandedValue($entity->id(), FALSE);
  }

  /**
   * HandleUpdate() must return FALSE and not persist for an unsupported entity type.
   */
  public function testHandleUpdateReturnsFalseForNonMenuLinkContent(): void {
    // User entity is a valid EntityInterface but not a MenuLinkContentInterface.
    $user = User::create(['name' => 'test_user_' . mt_rand()]);
    $user->save();

    $result = $this->column->handleUpdate($user, TRUE);

    $this->assertFalse($result, 'handleUpdate() must return FALSE for entity types it does not support.');
  }

  // Helpers.

  /**
   * Creates and saves a menu_link_content entity for testing.
   */
  private function createMenuLinkContent(bool $expanded): MenuLinkContentInterface {
    $entity = MenuLinkContent::create([
      'title' => 'Test link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'main',
      'expanded' => $expanded,
    ]);
    $entity->save();
    return $entity;
  }

  /**
   * Wraps a saved entity in a MenuLinkTreeElement via the plugin manager.
   *
   * Rebuilds link plugin definitions first so the entity-backed plugin is
   * discoverable. This is the same codepath the real tree traversal uses.
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
   * Reloads the entity and asserts its persisted expanded value.
   */
  private function assertPersistedExpandedValue(int|string $entity_id, bool $expected): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $storage->resetCache([$entity_id]);
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $reloaded */
    $reloaded = $storage->load($entity_id);
    $this->assertSame($expected, (bool) $reloaded->get('expanded')->value);
  }

}
