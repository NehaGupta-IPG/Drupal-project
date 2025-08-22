<?php

namespace Drupal\Tests\file\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Views;

/**
 * Tests file_update_10100().
 *
 * @group file
 */
class FileListingUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../system/tests/fixtures/update/drupal-9.4.0.filled.standard.php.gz',
    ];
  }

  /**
   * Test the pager type of "Files Overview" view.
   */
  public function testFilesViewPagerUpdate(): void {
    $view = Views::getView('files');
    $view->setDisplay('page_1');
    $this->assertEquals('mini', $view->display_handler->getOption('pager')['type']);

    $this->runUpdates();

    $view = Views::getView('files');
    $view->setDisplay('page_1');
    $this->assertEquals('full', $view->display_handler->getOption('pager')['type']);
  }

}
