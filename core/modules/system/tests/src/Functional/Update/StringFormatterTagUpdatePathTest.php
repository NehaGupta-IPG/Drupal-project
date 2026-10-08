<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding the tag setting to existing string and language formatters.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class StringFormatterTagUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schema and last-installed definitions for the raw fixture fields.
    // Load the historical configuration without saving it or the displays, so
    // current formatter defaults are not added before the update is tested.
    $update_manager = $this->container->get('entity.definition_update_manager');
    foreach (['field_string_tag', 'field_language_tag'] as $field_name) {
      $storage = FieldStorageConfig::load("node.$field_name");
      $update_manager->installFieldStorageDefinition($field_name, 'node', $storage->getProvider(), $storage);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/string-formatter-tag.php',
    ];
  }

  /**
   * Tests that updates persist defaults and preserve configured tags.
   */
  public function testRunUpdates(): void {
    $storage = $this->container->get('config.storage');
    $original = [];
    foreach (['default', 'teaser'] as $mode) {
      $name = "core.entity_view_display.node.article.$mode";
      $original[$mode] = $storage->read($name);
      foreach (['field_string_tag', 'field_language_tag'] as $field) {
        $this->assertArrayNotHasKey('tag', $original[$mode]['content'][$field]['settings']);
      }
    }
    $page = $storage->read('core.entity_view_display.node.page.default');
    $this->assertSame('h2', $page['content']['field_string_tag']['settings']['tag']);
    $this->assertSame('', $page['content']['field_language_tag']['settings']['tag']);

    $this->runUpdates();

    $storage = $this->container->get('config.storage');
    foreach ($original as $mode => $before) {
      $after = $storage->read("core.entity_view_display.node.article.$mode");
      foreach (['field_string_tag', 'field_language_tag'] as $field) {
        $this->assertArrayHasKey('tag', $after['content'][$field]['settings']);
        $this->assertSame('', $after['content'][$field]['settings']['tag']);
        $expected = $before['content'][$field];
        $expected['settings']['tag'] = '';
        $this->assertEquals($expected, $after['content'][$field]);
      }
      $this->assertEquals($before['content']['field_body'], $after['content']['field_body']);
    }
    $after = $storage->read('core.entity_view_display.node.page.default');
    $this->assertEquals($page['content']['field_string_tag'], $after['content']['field_string_tag']);
    $this->assertEquals($page['content']['field_language_tag'], $after['content']['field_language_tag']);
  }

}
