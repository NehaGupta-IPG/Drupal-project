<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the checkbox cell template.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridCellCheckboxTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'treegrid',
  ];

  /**
   * The checkbox carries the passed values, and its state follows the flags.
   */
  #[DataProvider('providerFlags')]
  public function testCheckboxMarkup(bool $value, bool $interactive): void {
    $build = [
      '#theme' => 'treegrid_cell_checkbox',
      '#value' => $value,
      '#label' => 'Row label',
      '#interactive' => $interactive,
      '#column_id' => 'enabled',
    ];
    $this->setRawContent((string) $this->container->get('renderer')->renderRoot($build));

    $inputs = $this->cssSelect('input[type="checkbox"].treegrid-cell-checkbox[data-treegrid-control]');
    $this->assertCount(1, $inputs);
    $this->assertSame('enabled', (string) $inputs[0]['data-column-id']);
    $this->assertSame('Row label', (string) $inputs[0]['aria-label']);
    $this->assertSame($value, isset($inputs[0]['checked']));
    $this->assertSame(!$interactive, isset($inputs[0]['disabled']));
  }

  /**
   * Data provider for testCheckboxMarkup().
   */
  public static function providerFlags(): \Generator {
    yield 'checked, interactive' => [TRUE, TRUE];
    yield 'checked, disabled' => [TRUE, FALSE];
    yield 'unchecked, interactive' => [FALSE, TRUE];
    yield 'unchecked, disabled' => [FALSE, FALSE];
  }

}
