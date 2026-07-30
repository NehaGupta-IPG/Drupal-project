<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path adding the disable_ajax_bookmarking setting.
 *
 * @see views_post_update_disable_ajax_bookmarking()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class DisableAjaxBookmarkingUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/disable-ajax-bookmarking.php',
    ];
  }

  /**
   * Tests that the disable_ajax_bookmarking setting is added properly.
   */
  public function testViewsPostUpdateDisableAjaxBookmarking(): void {
    $view = View::load('test_disable_ajax_bookmarking');
    $data = $view->toArray();
    foreach ($data['display'] as $display) {
      $this->assertArrayNotHasKey('disable_ajax_bookmarking', $display['display_options']);
    }

    $this->runUpdates();

    $view = View::load('test_disable_ajax_bookmarking');
    $data = $view->toArray();
    // Displays containing the use_ajax setting get the new setting.
    $this->assertFalse($data['display']['default']['display_options']['disable_ajax_bookmarking']);
    $this->assertFalse($data['display']['block_1']['display_options']['disable_ajax_bookmarking']);
    // Displays without the use_ajax setting are left alone.
    $this->assertArrayNotHasKey('disable_ajax_bookmarking', $data['display']['page_1']['display_options']);
  }

}
