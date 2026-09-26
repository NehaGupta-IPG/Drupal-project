<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the removal of the views.settings:ui.exposed_filter_any_label setting.
 *
 * @see views_post_update_remove_exposed_filter_any_label()
 */
#[Group('Update')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class ViewsRemoveExposedFilterAnyLabelUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path removing the exposed filter "Any" label setting.
   */
  public function testRunUpdates(): void {
    $this->assertSame('old_any', \Drupal::config('views.settings')->get('ui.exposed_filter_any_label'));

    $this->runUpdates();

    $ui = \Drupal::config('views.settings')->get('ui');
    $this->assertArrayNotHasKey('exposed_filter_any_label', $ui);
  }

}
