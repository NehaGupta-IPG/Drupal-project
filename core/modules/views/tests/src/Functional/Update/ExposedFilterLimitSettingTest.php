<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for the exposed filter limit setting.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class ExposedFilterLimitSettingTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Test that filter values are updated properly.
   *
   * @see views_post_update_exposed_filter_limit()
   */
  public function testViewsPostUpdateExposedFilterLimit(): void {
    $view = View::load('content');
    $display = $view->get('display');
    $this->assertArrayNotHasKey('limit', $display['default']['display_options']['filters']['type']['expose']);
    $this->assertArrayNotHasKey('limit', $display['default']['display_options']['filters']['langcode']['expose']);

    $this->runUpdates();

    $view = View::load('content');
    $display = $view->get('display');
    $this->assertFalse($display['default']['display_options']['filters']['type']['expose']['limit']);
    $this->assertFalse($display['default']['display_options']['filters']['langcode']['expose']['limit']);
    $this->assertArrayNotHasKey('limit', $display['default']['display_options']['filters']['title']['expose']);
    $this->assertArrayNotHasKey('limit', $display['default']['display_options']['filters']['status']['expose']);
  }

}
