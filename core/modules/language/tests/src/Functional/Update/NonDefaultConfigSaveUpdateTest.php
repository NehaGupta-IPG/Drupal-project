<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the update path in a different language.
 *
 * @group Update
 * @group legacy
 */
class NonDefaultConfigSaveUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-9.4.0.filled.standard.php.gz',
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-8.config-non-default-language.php',
    ];
  }

  /**
   * Tests language_post_update_language_select_widget().
   */
  public function testNonDefaultConfigOverride(): void {
    // Run the update.
    $this->runUpdates();

    // Load the override-free spanish view, make sure that the display title
    // of the override-free configuration is still spanish.
    $view = \Drupal::configFactory()->getEditable('views.view.spanish_view');
    $this->assertEquals('Maestro', $view->get('display.default.display_title'));
  }

}
