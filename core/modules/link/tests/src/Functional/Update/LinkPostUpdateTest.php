<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update path for link module.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class LinkPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests update hook setting handler for field type.
   */
  public function testLinkFieldTypeUpdate(): void {
    $config = $this->config('field.field.node.test_content_type.field_test_5');
    $this->assertEmpty($config->get('settings.handler'));
    $this->assertEmpty($config->get('settings.handler_settings'));

    // Run updates.
    $this->runUpdates();

    $config = $this->config('field.field.node.test_content_type.field_test_5');
    $this->assertSame('default', $config->get('settings.handler'));
    $this->assertIsArray($config->get('settings.handler_settings'));
  }

  /**
   * Tests update hook setting handler for field type.
   */
  public function testLinkWidgetMatchSettingsUpdate(): void {
    $config = $this->config('core.entity_form_display.node.test_content_type.default');
    $this->assertEmpty($config->get('content.field_test_5.settings.match_limit'));
    $this->assertEmpty($config->get('content.field_test_5.settings.match_operator'));

    // Run updates.
    $this->runUpdates();

    $config = $this->config('core.entity_form_display.node.test_content_type.default');
    $this->assertSame(10, $config->get('content.field_test_5.settings.match_limit'));
    $this->assertSame('CONTAINS', $config->get('content.field_test_5.settings.match_operator'));
  }

}
