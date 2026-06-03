<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_tree_ui\MenuTreeColumnManager;
use Drupal\menu_tree_ui\Plugin\MenuTreeColumn\MenuTreeColumnInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies plugin discovery wiring for MenuTreeColumnManager.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeColumnManagerTest extends KernelTestBase {

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
   * The column plugin manager under test.
   *
   * @var \Drupal\menu_tree_ui\MenuTreeColumnManager
   */
  private MenuTreeColumnManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->manager = $this->container->get('plugin.manager.menu_tree_column');
  }

  /**
   * The built-in 'expanded' column must be auto-discovered with correct metadata.
   */
  public function testBuiltInColumnIsDiscovered(): void {
    $definitions = $this->manager->getDefinitions();

    $this->assertArrayHasKey('expanded', $definitions, "'expanded' plugin must be discovered.");
    $this->assertSame('expanded', $definitions['expanded']['id']);
    $this->assertSame(10, $definitions['expanded']['weight']);
    $this->assertInstanceOf(
      TranslatableMarkup::class,
      $definitions['expanded']['title'],
    );
  }

  /**
   * GetSortedColumns() must return plugin instances sorted ascending by weight.
   */
  public function testGetSortedColumnsReturnsSortedInstances(): void {
    $columns = $this->manager->getSortedColumns();

    $this->assertNotEmpty($columns);
    $this->assertArrayHasKey('expanded', $columns);
    $this->assertInstanceOf(MenuTreeColumnInterface::class, $columns['expanded']);

    $definitions = $this->manager->getDefinitions();
    $prev_weight = PHP_INT_MIN;
    foreach (array_keys($columns) as $id) {
      $weight = $definitions[$id]['weight'] ?? 0;
      $this->assertGreaterThanOrEqual($prev_weight, $weight, "Columns must be sorted by weight ascending; $id is out of order.");
      $prev_weight = $weight;
    }
  }

  /**
   * CreateInstance() for the 'expanded' plugin must produce a usable instance.
   */
  public function testCreateInstanceProducesValidPlugin(): void {
    $instance = $this->manager->createInstance('expanded');

    $this->assertInstanceOf(MenuTreeColumnInterface::class, $instance);
    $this->assertInstanceOf(
      TranslatableMarkup::class,
      $instance->getHeader(),
    );
  }

  /**
   * HasDefinition() must match discovery truthfully.
   */
  public function testHasDefinition(): void {
    $this->assertTrue($this->manager->hasDefinition('expanded'));
    $this->assertFalse($this->manager->hasDefinition('nonexistent_column_xyz'));
  }

  /**
   * The alter hook tag is registered; invoking the alter must not error.
   *
   * We don't test that an external module's implementation runs — that's core's
   * responsibility. We verify only that our alterInfo() call wired up a valid
   * alter type so the manager won't crash on cache warm.
   */
  public function testAlterHookDoesNotError(): void {
    $definitions = $this->manager->getDefinitions();
    // Manually fire the alter (no modules implement it yet — just must not throw).
    $this->container->get('module_handler')->alter('menu_tree_column_info', $definitions);
    $this->assertArrayHasKey('expanded', $definitions);
  }

}
