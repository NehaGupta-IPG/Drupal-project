<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\MenuTreeRowContext;
use Drupal\menu_tree_ui\Plugin\TreegridColumn\OperationsColumn;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests OperationsColumn: an operations cell, read-only, depth cap.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class OperationsColumnTest extends KernelTestBase {

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
   * @var \Drupal\menu_tree_ui\Plugin\TreegridColumn\OperationsColumn
   */
  private OperationsColumn $column;

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
    $this->column = $manager->createInstance('operations');
  }

  /**
   * The column sorts last (after the data columns).
   */
  public function testSortsLast(): void {
    $columns = array_keys($this->container->get('plugin.manager.treegrid_column')->getSortedColumns());
    $this->assertSame('operations', end($columns), 'The operations column should sort last.');
  }

  /**
   * BuildCell() returns an operations element with Delete and Add child.
   *
   * These operations are asserted because their URLs resolve from entity and
   * route definitions present in this kernel environment; the Edit operation
   * depends on the link plugin's edit route (exercised on the full page by the
   * functional suite, and the no-edit-route path by
   * MenuTreeControllerColumnUpdateTest::testOrphanedLinkDoesNotFatalPage).
   */
  public function testBuildsOperationsElement(): void {
    $entity = $this->createMenuLinkContent();
    $context = new MenuTreeRowContext($this->elementFromEntity($entity, depth: 1), $entity);

    $cell = $this->column->buildCell($context);

    $this->assertSame('operations', $cell['operations']['#type']);
    $this->assertArrayHasKey('delete', $cell['operations']['#links']);
    $this->assertArrayHasKey('add-child', $cell['operations']['#links']);
  }

  /**
   * The cell holds no rendered string, so the renderer processes it.
   */
  public function testCellHoldsNoRenderedString(): void {
    $entity = $this->createMenuLinkContent();
    $context = new MenuTreeRowContext($this->elementFromEntity($entity, depth: 1), $entity);

    $cell = $this->column->buildCell($context);

    $rendered = [];
    array_walk_recursive($cell, function ($value, $key) use (&$rendered): void {
      if ($key === '#markup') {
        $rendered[] = $value;
      }
    });
    $this->assertSame([], $rendered);
  }

  /**
   * Without a content entity (a static link), there is no Delete operation.
   */
  public function testNoDeleteWithoutEntity(): void {
    $entity = $this->createMenuLinkContent();
    $context = new MenuTreeRowContext($this->elementFromEntity($entity, depth: 1), NULL);

    $cell = $this->column->buildCell($context);

    $this->assertArrayNotHasKey('delete', $cell['operations']['#links']);
  }

  /**
   * Omits "Add child" once a row is at the materialized-path depth cap.
   */
  public function testAddChildOmittedAtMaxDepth(): void {
    $entity = $this->createMenuLinkContent();
    $context = new MenuTreeRowContext(
      $this->elementFromEntity($entity, depth: MenuTreeStorage::MAX_DEPTH),
      $entity,
    );

    $cell = $this->column->buildCell($context);

    $this->assertArrayNotHasKey('add-child', $cell['operations']['#links']);
  }

  /**
   * The column is read-only: handleUpdate() always returns FALSE.
   */
  public function testIsReadOnly(): void {
    $entity = $this->createMenuLinkContent();

    $this->assertFalse($this->column->handleUpdate('menu_link_content:' . $entity->uuid(), TRUE));
  }

  // Helpers.

  /**
   * Creates and saves a menu_link_content entity for testing.
   */
  private function createMenuLinkContent(): MenuLinkContentInterface {
    $entity = MenuLinkContent::create([
      'title' => 'Test link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'main',
    ]);
    $entity->save();
    return $entity;
  }

  /**
   * Wraps a saved entity in a MenuLinkTreeElement at the given depth.
   */
  private function elementFromEntity(MenuLinkContentInterface $entity, int $depth): MenuLinkTreeElement {
    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $link_manager */
    $link_manager = $this->container->get('plugin.manager.menu.link');
    $link_manager->rebuild();

    $link = $link_manager->createInstance('menu_link_content:' . $entity->uuid());
    return new MenuLinkTreeElement($link, FALSE, $depth, FALSE, []);
  }

}
