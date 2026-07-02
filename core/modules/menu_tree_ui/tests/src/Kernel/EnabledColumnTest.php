<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\MenuTreeRowContext;
use Drupal\menu_tree_ui\Plugin\TreegridColumn\EnabledColumn;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests EnabledColumn cell data shape and persistence in isolation.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class EnabledColumnTest extends KernelTestBase {

  use UserCreationTrait;

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

    // Content-link updates check $entity->access('update'), which requires
    // 'administer menu' and, for a routed destination, 'link to any page' so
    // the check does not also depend on the destination route's own access.
    $this->setUpCurrentUser([], ['administer menu', 'link to any page']);

    // Static links are only present in the plugin manager's tree storage once
    // it has rebuilt from the *.links.menu.yml discovery.
    $this->container->get('plugin.manager.menu.link')->rebuild();

    /** @var \Drupal\treegrid\TreegridColumnManager $manager */
    $manager = $this->container->get('plugin.manager.treegrid_column');
    $this->column = $manager->createInstance('enabled');
  }

  /**
   * The column sorts ahead of "Operations".
   */
  public function testSortsBeforeOperations(): void {
    $columns = array_keys($this->container->get('plugin.manager.treegrid_column')->getSortedColumns());
    $this->assertLessThan(
      array_search('operations', $columns, TRUE),
      array_search('enabled', $columns, TRUE),
      'The enabled column should sort before the operations column.',
    );
  }

  /**
   * GetCellData() returns a checkbox for every link, including leaves.
   */
  public function testGetCellDataAppliesToLeaves(): void {
    $entity = $this->createMenuLinkContent(enabled: TRUE);
    $element = $this->elementFromEntity($entity, hasChildren: FALSE);

    $cell = $this->column->getCellData(new MenuTreeRowContext($element, $entity));

    $this->assertSame('checkbox', $cell['type']);
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

    $cell = $this->column->getCellData(new MenuTreeRowContext($element, $entity));

    $this->assertFalse($cell['value']);
  }

  /**
   * A static MenuLinkDefault link's checkbox is interactive.
   */
  public function testGetCellDataInteractiveForStaticLink(): void {
    $link = $this->container->get('plugin.manager.menu.link')->createInstance('user.logout');
    $element = new MenuLinkTreeElement($link, FALSE, 1, FALSE, []);

    $cell = $this->column->getCellData(new MenuTreeRowContext($element, NULL));

    $this->assertTrue($cell['interactive']);
    $this->assertSame('user.logout', $cell['link_id']);
  }

  /**
   * HandleUpdate() to FALSE must persist and return TRUE.
   */
  public function testHandleUpdatePersistsFalse(): void {
    $entity = $this->createMenuLinkContent(enabled: TRUE);

    $result = $this->column->handleUpdate('menu_link_content:' . $entity->uuid(), FALSE);

    $this->assertTrue($result);
    $this->assertPersistedEnabledValue($entity->id(), FALSE);
  }

  /**
   * HandleUpdate() to TRUE must persist and return TRUE.
   */
  public function testHandleUpdatePersistsTrue(): void {
    $entity = $this->createMenuLinkContent(enabled: FALSE);

    $result = $this->column->handleUpdate('menu_link_content:' . $entity->uuid(), TRUE);

    $this->assertTrue($result);
    $this->assertPersistedEnabledValue($entity->id(), TRUE);
  }

  /**
   * HandleUpdate() on a static MenuLinkDefault link persists via the link manager.
   */
  public function testHandleUpdateStaticLinkPersistsViaLinkManager(): void {
    $link_manager = $this->container->get('plugin.manager.menu.link');
    $this->assertTrue($link_manager->createInstance('user.logout')->isEnabled());

    $result = $this->column->handleUpdate('user.logout', FALSE);

    $this->assertTrue($result);
    $this->assertFalse($link_manager->createInstance('user.logout')->isEnabled());
  }

  /**
   * HandleUpdate() must refuse a content link with a pending revision.
   */
  public function testHandleUpdateRefusesPendingRevision(): void {
    $entity = $this->createMenuLinkContent(enabled: TRUE);
    /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $pending_revision = $storage->createRevision($entity, FALSE);
    $pending_revision->save();

    $result = $this->column->handleUpdate('menu_link_content:' . $entity->uuid(), FALSE);

    $this->assertFalse($result);
    $this->assertPersistedEnabledValue($entity->id(), TRUE);
  }

  /**
   * HandleUpdate() must refuse a content link the current user cannot edit.
   */
  public function testHandleUpdateRefusesWithoutEntityAccess(): void {
    $entity = $this->createMenuLinkContent(enabled: TRUE);
    $this->setCurrentUser($this->createUser());

    $result = $this->column->handleUpdate('menu_link_content:' . $entity->uuid(), FALSE);

    $this->assertFalse($result);
    $this->assertPersistedEnabledValue($entity->id(), TRUE);
  }

  /**
   * HandleUpdate() must return FALSE for a link ID with no definition.
   */
  public function testHandleUpdateReturnsFalseForUnknownLinkId(): void {
    $result = $this->column->handleUpdate('nonexistent_plugin_id', TRUE);

    $this->assertFalse($result, 'handleUpdate() must return FALSE for a link ID with no definition.');
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
