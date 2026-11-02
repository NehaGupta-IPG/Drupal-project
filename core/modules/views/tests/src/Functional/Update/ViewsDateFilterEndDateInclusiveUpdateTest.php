<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding the 'end_date_inclusive' option to date filters.
 *
 * @see views_post_update_date_filter_end_date_inclusive()
 */
#[Group('Update')]
#[CoversFunction('views_post_update_date_filter_end_date_inclusive')]
#[RunTestsInSeparateProcesses]
class ViewsDateFilterEndDateInclusiveUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/filter-date-end-date-inclusive.php',
    ];
  }

  /**
   * Tests that 'end_date_inclusive' is added to date filters.
   */
  public function testViewsPostUpdateDateFilterEndDateInclusive(): void {
    $view = View::load('test_filter_date_end_date_inclusive');
    $data = $view->toArray();
    $filters = $data['display']['default']['display_options']['filters'];

    $this->assertArrayNotHasKey('end_date_inclusive', $filters['created']);
    $this->assertTrue($filters['changed']['end_date_inclusive']);

    $this->runUpdates();

    $view = View::load('test_filter_date_end_date_inclusive');
    $data = $view->toArray();
    $filters = $data['display']['default']['display_options']['filters'];

    // Date filters without the option should keep excluding the end date.
    $this->assertFalse($filters['created']['end_date_inclusive']);
    // Date filters with the option should be unchanged.
    $this->assertTrue($filters['changed']['end_date_inclusive']);
    // Other filters should not get the option.
    $this->assertArrayNotHasKey('end_date_inclusive', $filters['nid']);
  }

}
