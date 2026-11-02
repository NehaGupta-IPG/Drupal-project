<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the language formatter update.
 *
 * The language formatter update preserves existing configuration.
 *
 * @see language_post_update_language_component_plain_text()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class NodeLanguageFormatterUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../../../language/tests/fixtures/update/language.php',
      __DIR__ . '/../../../../../language/tests/fixtures/update/node_page_display.php',
    ];
  }

  /**
   * Tests language_post_update_language_component_plain_text().
   */
  public function testLanguagePostUpdateLanguageComponent(): void {
    // Ensure config has link enabled and label hidden.
    $config = $this->config('core.entity_view_display.node.page.default');
    $this->assertTrue($config->get('content.langcode.settings.link_to_entity'));
    $this->assertEquals('hidden', $config->get('content.langcode.label'));

    $this->runUpdates();

    // Confirm that the existing formatter settings and label are preserved.
    $updated_config = $this->config('core.entity_view_display.node.page.default');
    $this->assertTrue($updated_config->get('content.langcode.settings.link_to_entity'));
    $this->assertEquals('hidden', $updated_config->get('content.langcode.label'));
  }

}
