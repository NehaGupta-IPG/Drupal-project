<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\treegrid\TreegridRows;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the rows response builder.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridRowsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'treegrid',
  ];

  /**
   * The build uses the rows theme hook with the rows as given, uncached.
   */
  public function testBuildCarriesRowsAndDisablesCaching(): void {
    $rows = [['#markup' => '<tr><td>a</td></tr>']];
    $build = TreegridRows::build($rows);

    $this->assertSame('treegrid_rows', $build['#theme']);
    $this->assertSame($rows, $build['#rows']);
    $this->assertSame(0, $build['#cache']['max-age']);
  }

  /**
   * The marker header is there with and without a total.
   */
  public function testMarkerHeaderIsAlwaysSet(): void {
    $this->assertContains(['X-Treegrid-Rows', '1'], TreegridRows::build([])['#attached']['http_header']);
    $this->assertContains(['X-Treegrid-Rows', '1'], TreegridRows::build([], 5)['#attached']['http_header']);
  }

  /**
   * The total and next id headers appear only when given.
   */
  public function testOptionalHeadersAppearOnlyWhenGiven(): void {
    $names = fn (array $build): array => array_column($build['#attached']['http_header'], 0);

    $this->assertSame(['X-Treegrid-Rows'], $names(TreegridRows::build([])));
    $this->assertSame(['X-Treegrid-Rows', 'X-Treegrid-Total'], $names(TreegridRows::build([], 0)));
    $this->assertSame(['X-Treegrid-Rows', 'X-Treegrid-Next-Id'], $names(TreegridRows::build([], NULL, 'a')));
    $this->assertContains(['X-Treegrid-Total', '205'], TreegridRows::build([], 205)['#attached']['http_header']);
  }

  /**
   * The next id is percent-encoded, so any plugin id survives a header.
   */
  public function testNextIdIsPercentEncoded(): void {
    $id = 'menu_link_content:uuid-é';
    $headers = TreegridRows::build([], NULL, $id)['#attached']['http_header'];

    $this->assertContains(['X-Treegrid-Next-Id', rawurlencode($id)], $headers);
    $this->assertSame('menu_link_content%3Auuid-%C3%A9', rawurlencode($id));
  }

  /**
   * The rows render inside a table body marked for the client.
   */
  public function testRowsRenderInsideTheWrapper(): void {
    $build = TreegridRows::build([['#markup' => '<tr data-id="x"><td>a</td></tr>']]);
    $this->setRawContent((string) $this->container->get('renderer')->renderRoot($build));

    $this->assertCount(1, $this->cssSelect('table > tbody[data-treegrid-rows] > tr[data-id="x"]'));
  }

}
