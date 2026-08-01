<?php

declare(strict_types=1);

namespace Drupal\Tests\text\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the exclude_html_tags setting is added.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class ExcludeHtmlTagsSettingUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that exclude_html_tags get added.
   */
  public function testRunUpdates(): void {
    $display = \Drupal::config('core.entity_view_display.node.article.teaser');
    $components = $display->get('content');
    $this->assertArrayHasKey('body', $components);
    $this->assertSame('text_summary_or_trimmed', $components['body']['type']);
    $this->assertArrayNotHasKey('exclude_html_tags', $components['body']['settings']);

    $this->runUpdates();

    $display = \Drupal::config('core.entity_view_display.node.article.teaser');
    $components = $display->get('content');
    $this->assertArrayHasKey('exclude_html_tags', $components['body']['settings']);
    $this->assertFalse($components['body']['settings']['exclude_html_tags']);
  }

}
