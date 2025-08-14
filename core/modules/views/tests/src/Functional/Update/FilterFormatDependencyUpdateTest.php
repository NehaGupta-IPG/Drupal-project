<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the upgrade path for fixing dependencies on filter formats.
 *
 * @group Update
 */
class FilterFormatDependencyUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/test_filter_format_dependencies.php',
    ];
  }

  /**
   * @covers views_post_update_views_filter_format_dependencies
   */
  public function testViewsFieldPluginConversion(): void {
    $view = View::load('test_filter_format_dependencies');
    $data = $view->toArray();
    $this->assertArrayNotHasKey('config', $data['dependencies']);

    $this->runUpdates();

    $view = View::load('test_filter_format_dependencies');
    $data = $view->toArray();
    $this->assertEquals(['filter.format.basic_html'], $data['dependencies']['config']);
  }

}
