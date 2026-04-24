<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the addition of the default 'not' config to string arguments.
 *
 * This config appears in the user interface as 'exclude'.
 *
 * @see views_post_update_string_argument_exclude()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class StringArgumentExcludeUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/string-argument-exclude.php',
    ];
  }

  /**
   * Tests the upgrade path adding string argument default 'not' config.
   */
  public function testViewsStringArgumentExcludeConfigUpdate(): void {
    $view = View::load('test_argument_string_exclude_update');
    $data = $view->toArray();
    $this->assertEquals('string', $data['display']['default']['display_options']['arguments']['body_value']['plugin_id']);
    $this->assertArrayNotHasKey('not', $data['display']['default']['display_options']['arguments']['body_value']);

    $this->runUpdates();

    $view = View::load('test_argument_string_exclude_update');
    $data = $view->toArray();
    $this->assertArrayHasKey('not', $data['display']['default']['display_options']['arguments']['body_value']);
    $this->assertEquals(FALSE, $data['display']['default']['display_options']['arguments']['body_value']['not']);
  }

}
