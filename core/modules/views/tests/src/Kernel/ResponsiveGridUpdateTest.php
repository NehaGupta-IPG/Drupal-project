<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel;

use Drupal\views\Entity\View;
use Drupal\views\ViewsConfigUpdater;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for adding custom classes to responsive grid.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class ResponsiveGridUpdateTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_grid_responsive'];

  /**
   * Tests the update path for adding custom classes.
   */
  public function testResponsiveGridClassUpdate(): void {
    /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
    $view_config_updater = $this->container->get(ViewsConfigUpdater::class);

    // Setup: Load a view and force it into an "old" state.
    // We disable strict schema temporarily because saving the view without
    // the new keys would otherwise throw a SchemaIncompleteException.
    $this->strictConfigSchema = FALSE;
    $view = View::load('test_grid_responsive');
    $display = &$view->getDisplay('default');

    unset($display['display_options']['style']['options']['grid_wrapper_class_custom']);
    unset($display['display_options']['style']['options']['grid_cell_class_custom']);
    $view->save();

    // Enable strict validation for the update process.
    $this->strictConfigSchema = TRUE;

    // Verify the service detects the need for an update.
    $this->assertTrue($view_config_updater->needsResponsiveGridClassUpdate($view));

    // Save the view.
    $view->save();

    // Final assertions to ensure values are correct.
    $view = View::load('test_grid_responsive');
    $options = $view->getDisplay('default')['display_options']['style']['options'];

    $this->assertArrayHasKey('grid_wrapper_class_custom', $options);
    $this->assertArrayHasKey('grid_cell_class_custom', $options);
    $this->assertSame('', $options['grid_wrapper_class_custom']);
    $this->assertSame('', $options['grid_cell_class_custom']);
  }

}
