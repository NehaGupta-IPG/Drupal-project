<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface;
use Drupal\treegrid\TreegridColumnManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the generic TreegridColumn plugin manager.
 *
 * Discovery, weight sorting, instance creation, the info-alter hook, and the
 * within-request memoization — exercised with the treegrid_test stub columns
 * rather than through any consumer, so this is treegrid's own coverage.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridColumnManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid',
    'treegrid_test',
  ];

  /**
   * The plugin manager under test.
   */
  private TreegridColumnManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = $this->container->get('plugin.manager.treegrid_column');
  }

  /**
   * Columns are discovered from the attribute with their declared metadata.
   */
  public function testDiscoversColumnsWithMetadata(): void {
    $definitions = $this->manager->getDefinitions();

    $this->assertArrayHasKey('treegrid_test_a', $definitions);
    $this->assertArrayHasKey('treegrid_test_b', $definitions);
    $this->assertSame(5, $definitions['treegrid_test_a']['weight']);
    $this->assertSame(10, $definitions['treegrid_test_b']['weight']);
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['treegrid_test_a']['title']);
  }

  /**
   * GetSortedColumns() returns plugin instances keyed by ID, sorted by weight.
   */
  public function testGetSortedColumnsAscendingByWeight(): void {
    $columns = $this->manager->getSortedColumns();

    $this->assertSame(['treegrid_test_a', 'treegrid_test_b'], array_keys($columns));
    $this->assertInstanceOf(TreegridColumnInterface::class, $columns['treegrid_test_a']);
    $this->assertInstanceOf(TreegridColumnInterface::class, $columns['treegrid_test_b']);
  }

  /**
   * CreateInstance() produces a usable plugin exposing its header.
   */
  public function testCreateInstanceProducesValidPlugin(): void {
    $instance = $this->manager->createInstance('treegrid_test_a');

    $this->assertInstanceOf(TreegridColumnInterface::class, $instance);
    $this->assertInstanceOf(TranslatableMarkup::class, $instance->getHeader());
  }

  /**
   * HasDefinition() matches discovery truthfully.
   */
  public function testHasDefinition(): void {
    $this->assertTrue($this->manager->hasDefinition('treegrid_test_a'));
    $this->assertFalse($this->manager->hasDefinition('nonexistent_column_xyz'));
  }

  /**
   * The treegrid_column_info alter hook runs against discovered definitions.
   */
  public function testAlterHookModifiesDefinitions(): void {
    $definitions = $this->manager->getDefinitions();

    // treegrid_test's hook_treegrid_column_info_alter() sets this marker.
    $this->assertTrue($definitions['treegrid_test_a']['altered'] ?? FALSE);
  }

  /**
   * GetSortedColumns() returns the same instances within a request.
   */
  public function testGetSortedColumnsAreStableWithinRequest(): void {
    $first = $this->manager->getSortedColumns();
    $second = $this->manager->getSortedColumns();

    $this->assertSame($first['treegrid_test_a'], $second['treegrid_test_a']);
  }

}
