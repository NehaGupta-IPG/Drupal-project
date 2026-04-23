<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for auto_create_check_access on entity reference fields.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class AutocreateAccessUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      // @todo Can we use 11.3.0 already? https://www.drupal.org/project/drupal/issues/3569127 landed.
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../../../system/tests/fixtures/update/uninstall-history.php',
      __DIR__ . '/../../../../../system/tests/fixtures/update/uninstall-contact.php',
    ];
  }

  /**
   * Tests that existing entity reference fields get auto_create_check_access set to FALSE.
   */
  public function testRunUpdates(): void {
    $config = \Drupal::config('field.field.node.article.field_tags');
    $handler_settings = $config->get('settings.handler_settings');
    // Before the update, auto_create is set but auto_create_check_access is not.
    $this->assertTrue($handler_settings['auto_create']);
    $this->assertArrayNotHasKey('auto_create_check_access', $handler_settings);

    $this->runUpdates();

    $config = \Drupal::config('field.field.node.article.field_tags');
    $handler_settings = $config->get('settings.handler_settings');
    // After the update, auto_create_check_access is explicitly set to FALSE.
    $this->assertTrue($handler_settings['auto_create']);
    $this->assertFalse($handler_settings['auto_create_check_access']);
  }

}
