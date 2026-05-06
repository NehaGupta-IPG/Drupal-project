<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views_grid\Plugin\views\style\Grid;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for enabling views_grid module automatically.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class ViewsGridModuleUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/views-grid-test.php',
    ];
  }

  /**
   * Tests automatic enabling of views_grid module for existing grid views.
   */
  public function testViewsGridModuleEnable(): void {
    // Before running updates, verify the view exists and uses grid style.
    $view_config = $this->config('views.view.test_grid_upgrade');
    $this->assertNotEmpty($view_config->get('display.default.display_options.style'));
    $this->assertEquals('grid', $view_config->get('display.default.display_options.style.type'));

    // Verify views_grid module is not yet enabled.
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('views_grid'));

    // Run updates.
    $this->runUpdates();

    // Verify views_grid module is now enabled.
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('views_grid'));

    // Verify the view still exists and works.
    $view_config = $this->config('views.view.test_grid_upgrade');
    $this->assertNotEmpty($view_config->get('display.default.display_options.style'));
    $this->assertEquals('grid', $view_config->get('display.default.display_options.style.type'));

    // Load the view and verify it has the correct style plugin.
    $view = \Drupal::service('entity_type.manager')
      ->getStorage('view')
      ->load('test_grid_upgrade');
    $this->assertNotNull($view);

    $executable = $view->getExecutable();
    $executable->setDisplay('default');
    $executable->initStyle();

    // The view should have the grid style plugin.
    $this->assertInstanceOf(
      // @phpstan-ignore-next-line classConstant.deprecatedClass (Testing deprecated functionality)
      Grid::class,
      $executable->style_plugin
    );
  }

  /**
   * Tests that views_grid module is not enabled if no grid views exist.
   */
  public function testViewsGridModuleNotEnabledWhenNoGridViews(): void {
    // Remove the grid view from config before running updates.
    $this->config('views.view.test_grid_upgrade')->delete();

    // Verify views_grid module is not enabled.
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('views_grid'));

    // Run updates.
    $this->runUpdates();

    // Verify views_grid module is still not enabled since no grid views exist.
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('views_grid'));
  }

}
