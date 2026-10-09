<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding the tag setting to existing string formatters.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class StringFormatterTagUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that the update adds an empty tag and keeps other settings.
   */
  public function testRunUpdates(): void {
    $name = 'core.entity_view_display.node.test_content_type.default';
    $before = $this->config($name)->get('content.field_test_19');
    $this->assertSame('string', $before['type']);
    $this->assertArrayNotHasKey('tag', $before['settings']);

    $this->runUpdates();

    $after = $this->config($name)->get('content.field_test_19');
    $this->assertSame('', $after['settings']['tag']);
    $this->assertSame($before['settings']['link_to_entity'], $after['settings']['link_to_entity']);
  }

}
