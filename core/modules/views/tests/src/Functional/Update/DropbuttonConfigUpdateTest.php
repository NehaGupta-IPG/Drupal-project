<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\views\Entity\View;

/**
 * Tests the Views dropbutton_type configuration update.
 *
 * @see views_post_update_dropbutton_type()
 */
#[Group('Update')]
#[CoversFunction('views_post_update_dropbutton_type')]
#[RunTestsInSeparateProcesses]
class DropbuttonConfigUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/dropbutton-type.php',
    ];
  }

  /**
   * Tests updating the dropbutton_type field configuration.
   */
  public function testDropbuttonConfigUpdate(): void {
    $view = View::load('test_dropbutton_update');
    $data = $view->toArray();
    $this->assertArrayNotHasKey('dropbutton_type', $data['display']['default']['display_options']['fields']['dropbutton'],
      'Pre-update: dropbutton_type is not set.'
    );

    $this->runUpdates();

    $view = View::load('test_dropbutton_update');
    $data = $view->toArray();
    // Ensure that the dropbutton_type key has been set.
    $this->assertArrayHasKey('dropbutton_type', $data['display']['default']['display_options']['fields']['dropbutton'],
      'Post-update: dropbutton_type has been set.'
    );
  }

}
